(function(global) {
    'use strict';

    const DEFAULT_PARAMS = {
        tempoDisponivelTurnoMin: null,
        demandaPlanejada: null,
        unidadeDemanda: '',
        metaEficiencia: null,
        toleranciaBalanceamento: null
    };

    let lastKey = '';
    let lastResult = null;

    function toNumber(value, fallback = null) {
        if (typeof value === 'string') {
            value = value.replace(',', '.').trim();
        }
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function normalizeParams(params = {}) {
        return {
            tempoDisponivelTurnoMin: positiveOrNull(params.tempoDisponivelTurnoMin ?? params.tempo_disponivel_turno_min),
            demandaPlanejada: positiveOrNull(params.demandaPlanejada ?? params.demanda_planejada),
            unidadeDemanda: String(params.unidadeDemanda ?? params.unidade_demanda ?? '').trim(),
            metaEficiencia: percentOrNull(params.metaEficiencia ?? params.meta_eficiencia),
            toleranciaBalanceamento: percentOrNull(params.toleranciaBalanceamento ?? params.tolerancia_balanceamento)
        };
    }

    function positiveOrNull(value) {
        const number = toNumber(value, null);
        return number !== null && number > 0 ? number : null;
    }

    function percentOrNull(value) {
        const number = toNumber(value, null);
        if (number === null) return null;
        return Math.min(100, Math.max(0, number));
    }

    function ritmoPosto(posto) {
        const pessoas = Math.max(1, parseInt(posto.pessoas || 1, 10) || 1);
        const ritmo = positiveOrNull(posto.ritmo);
        if (ritmo !== null) return ritmo;

        const tcContentor = positiveOrNull(posto.tcContentor ?? posto.tc_contentor ?? posto.tc);
        return tcContentor !== null ? tcContentor / pessoas : null;
    }

    function statusPosto({ ritmo, takt, tolerancia, isGargalo }) {
        if (ritmo === null) return 'sem_dados';
        if (takt === null) return 'sem_dados';

        const toleranceSeconds = takt * ((tolerancia ?? 10) / 100);
        const delta = ritmo - takt;
        if (isGargalo && delta > toleranceSeconds) return 'gargalo';
        if (delta > toleranceSeconds) return 'gargalo';
        if (delta < -toleranceSeconds) return 'ocioso';
        if (Math.abs(delta) <= toleranceSeconds) return 'balanceado';
        return 'atencao';
    }

    function statusLabel(status) {
        return {
            sem_dados: 'Sem dados',
            balanceado: 'Balanceado',
            atencao: 'Atenção',
            gargalo: 'Gargalo/Sobrecarga',
            ocioso: 'Ocioso'
        }[status] || 'Sem dados';
    }

    function analyze(input = {}) {
        const params = normalizeParams(input.params || {});
        const postos = Array.isArray(input.postos) ? input.postos : [];
        const kgPorCtt = positiveOrNull(input.kgPorCtt ?? input.kg_por_ctt);
        const key = JSON.stringify({
            params,
            kgPorCtt,
            postos: postos.map(posto => ({
                id: posto.id,
                name: posto.name,
                pessoas: posto.pessoas,
                tc: posto.tc,
                tcContentor: posto.tcContentor ?? posto.tc_contentor,
                ritmo: posto.ritmo,
                atividades: (posto.atividades || []).map(item => item.id || item.nome || '')
            }))
        });
        if (key === lastKey && lastResult) {
            return lastResult;
        }

        const eficienciaMeta = params.metaEficiencia !== null ? params.metaEficiencia / 100 : 1;
        const tempoDisponivelSeg = params.tempoDisponivelTurnoMin !== null ? params.tempoDisponivelTurnoMin * 60 : null;
        const takt = tempoDisponivelSeg !== null && params.demandaPlanejada !== null
            ? (tempoDisponivelSeg * eficienciaMeta) / params.demandaPlanejada
            : null;

        const base = postos
            .filter(posto => (posto.type || 'node') === 'node')
            .map(posto => {
                const ritmo = ritmoPosto(posto);
                const capacidadeHora = ritmo !== null ? 3600 / ritmo : null;
                const capacidadeKgHora = capacidadeHora !== null && kgPorCtt !== null ? capacidadeHora * kgPorCtt : null;
                return {
                    id: String(posto.id || ''),
                    drawflowId: posto.drawflow_id,
                    nome: posto.name || 'Posto sem nome',
                    pessoas: Math.max(1, parseInt(posto.pessoas || 1, 10) || 1),
                    tc: positiveOrNull(posto.tc),
                    tcContentor: positiveOrNull(posto.tcContentor ?? posto.tc_contentor),
                    ritmo,
                    takt,
                    capacidadeHora,
                    capacidadeKgHora,
                    atividades: Array.isArray(posto.atividades) ? posto.atividades : []
                };
            });

        const validos = base.filter(row => row.ritmo !== null);
        const gargalo = validos.length
            ? validos.reduce((current, row) => row.ritmo > current.ritmo ? row : current, validos[0])
            : null;
        const capacidadeLinhaHora = validos.length
            ? Math.min(...validos.map(row => row.capacidadeHora))
            : null;
        const capacidadeLinhaKgHora = capacidadeLinhaHora !== null && kgPorCtt !== null ? capacidadeLinhaHora * kgPorCtt : null;
        const ritmoMedio = validos.length
            ? validos.reduce((sum, row) => sum + row.ritmo, 0) / validos.length
            : null;
        const eficienciaPorGargalo = takt !== null && gargalo ? Math.min(1, takt / gargalo.ritmo) : null;
        const eficienciaPorDistribuicao = gargalo && ritmoMedio !== null ? Math.min(1, ritmoMedio / gargalo.ritmo) : null;
        const eficienciaEstimativa = eficienciaPorGargalo !== null && eficienciaPorDistribuicao !== null
            ? ((eficienciaPorGargalo + eficienciaPorDistribuicao) / 2) * 100
            : null;

        const analisados = base.map(row => {
            const sobrecarga = takt !== null && row.ritmo !== null && row.ritmo > takt ? row.ritmo - takt : null;
            const ociosidade = takt !== null && row.ritmo !== null && row.ritmo < takt ? takt - row.ritmo : null;
            const status = statusPosto({
                ritmo: row.ritmo,
                takt,
                tolerancia: params.toleranciaBalanceamento,
                isGargalo: !!gargalo && row.id === gargalo.id
            });
            return {
                ...row,
                ociosidade,
                sobrecarga,
                status,
                statusLabel: statusLabel(status),
                isGargalo: !!gargalo && row.id === gargalo.id
            };
        });

        lastKey = key;
        lastResult = {
            params,
            resumo: {
                takt,
                gargalo: gargalo ? gargalo.nome : '',
                capacidadeLinhaHora,
                capacidadeLinhaKgHora,
                kgPorCtt,
                eficienciaEstimativa,
                postosAnalisados: validos.length,
                postosSemDados: base.length - validos.length,
                unidadeDemanda: params.unidadeDemanda
            },
            postos: analisados
        };
        return lastResult;
    }

    global.BalanceamentoService = {
        defaultParams: DEFAULT_PARAMS,
        normalizeParams,
        analyze
    };
})(window);
