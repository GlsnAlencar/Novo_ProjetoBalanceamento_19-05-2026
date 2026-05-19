# Módulo Calibradora 📦

## Visão Geral

O **Módulo Calibradora** é um sistema isolado de distribuição operacional para calibradoras de fruta. Seu objetivo é:

- Cadastrar faixas de peso
- Configurar produtos operacionais por faixa
- Registrar lotes processados
- Distribuir gramas/percentuais de forma manual
- Gerar resultado operacional agregado

**Isolamento Total:** Este módulo não interfere com nenhum outro sistema legado (Cronoanálise, Árvore de Estrutura, Balanceamento, MES/APS).

---

## Estrutura do Módulo

```
/public/reformulacao/calibradora/
├── index.php                          # Dashboard/Hub principal
├── models/                            # Entidades do domínio
│   ├── FaixaPeso.php
│   ├── ConfiguracaoEmbalamento.php
│   ├── RegistroLote.php
│   └── DistribuicaoLote.php
├── repositories/                      # Acesso a dados (JSON)
│   ├── BaseRepository.php
│   ├── FaixaPesoRepository.php
│   ├── ConfiguracaoEmbalamentoRepository.php
│   ├── RegistroLoteRepository.php
│   └── DistribuicaoLoteRepository.php
├── services/                          # Lógica de negócio
│   └── CalbradoraService.php
├── controllers/                       # Processamento de requisições
│   └── CalbradoraController.php
└── views/                             # Telas (5 etapas)
    ├── etapa1_faixas.php
    ├── etapa2_configuracao.php
    ├── etapa3_registro_lote.php
    ├── etapa4_distribuicao.php
    └── etapa5_resultado.php

/data/reformulacao/calibradora/
├── faixas_peso.json
├── configuracoes_embalamento.json
├── registros_lote.json
├── distribuicoes_lote.json
└── (arquivos .lock para controle de concorrência)
```

---

## 5 Etapas de Funcionalidade

### ETAPA 1: Cadastro de Faixas de Peso

**Arquivo:** `etapa1_faixas.php`

Cadastro do conjunto fixo de faixas da calibradora.

**Campos:**
- ID (automático)
- Descrição (ex: REFUGO, 14, 12, 10...)
- Peso Inicial (em gramas)
- Peso Final (em gramas)
- Nome Configuração (agrupamento)

**Regras:**
- Validação automática de sobreposição
- Ordenação por peso inicial
- Múltiplas operações (criar, editar, deletar)

**Exemplo:**
```
Nome Configuração: Exportação 4KG Palmer

ID | Descrição | Peso Inicial | Peso Final
1  | REFUGO    | 50           | 150
2  | 14        | 150          | 270
3  | 12        | 270          | 385
4  | 10        | 385          | 445
```

---

### ETAPA 2: Configuração de Embalamento por Faixa

**Arquivo:** `etapa2_configuracao.php`

Definição do Produto Operacional gerado por cada faixa.

**Campos:**
- Nome da Configuração
- Faixa de Peso Base (seleção)
- Mapeamento: GR → Produto Operacional

**Regras:**
- Carregamento automático de faixas ao selecionar configuração
- Produto Operacional é apenas referência de texto nesta etapa
- Sem integração com cronoanálise ainda

**Exemplo:**
```
Nome: Exportação 4KG Palmer
Faixa Base: Exportação 4KG Palmer

GR | Descrição | Produto Operacional
1  | REFUGO    | Refugo
2  | 14        | Descarte Polpa
3  | 12        | Caixa 4kg padrão
4  | 10        | Caixa 4kg padrão
```

---

### ETAPA 3: Registro do Lote

**Arquivo:** `etapa3_registro_lote.php`

Registrar o lote processado pela calibradora.

**Campos:**
- Controle (obrigatório) ✓
- Configuração de Embalamento (opcional)
- Programa (opcional)
- Partida (opcional)
- Produtor (opcional)
- Variedade (opcional)
- Classe (opcional)
- Observações (opcional)

**Regras:**
- Apenas Controle é obrigatório
- Salvar em rascunho antes de finalizar
- Evitar duplicação de controles

**Estados:**
- **rascunho**: Lote em edição
- **salvo**: Lote finalizado

---

### ETAPA 4: Distribuição do Lote

**Arquivo:** `etapa4_distribuicao.php`

Registrar os gramas/percentuais produzidos pela calibradora.

**Campos:**
- GR (índice)
- Descrição (automática da faixa)
- Faixa Peso (automática da faixa)
- Produto Operacional (automático da configuração)
- Gramas (entrada manual)
- Percentual (cálculo automático)

**Funcionalidades:**
- Entrada manual de gramas
- Cálculo automático de percentuais
- Validação de soma a 100%
- Salvamento da distribuição

**Sem OCR nesta etapa** - apenas entrada manual.

---

### ETAPA 5: Resultado Operacional

**Arquivo:** `etapa5_resultado.php`

Gerar o perfil operacional do lote.

**Saída:**
```
Produto Operacional | Percentual
Caixa 4kg padrão    | 42%
Descarte Polpa      | 31%
Refugo              | 8%
```

**Características:**
- Agregação automática por Produto Operacional
- Visualização com gráfico de barras
- Preparação para integração futura com:
  - Cronoanálise
  - Árvore de Estrutura
  - Balanceamento

---

## Arquitetura

### Padrão de Camadas

```
View (etapa*.php)
    ↓
Controller (CalbradoraController)
    ↓
Service (CalbradoraService)
    ↓
Repository (FaixaPesoRepository, etc)
    ↓
Model (FaixaPeso, ConfiguracaoEmbalamento, etc)
    ↓
Storage (JSON files)
```

### Isolamento por Referência

- **Sem dependências diretas** entre módulos
- **Integração por IDs e referências** (não por objetos)
- **Dados em arquivos JSON separados** (`/data/reformulacao/calibradora/`)
- **Namespaces isolados** (`CalbradoraModule\*`)

---

## Dados de Exemplo

### faixas_peso.json
```json
{
  "version": 1,
  "created_at": "2026-05-14 10:00:00",
  "updated_at": "2026-05-14 10:00:00",
  "faixas": [
    {
      "id": 1,
      "descricao": "REFUGO",
      "peso_inicial": 50,
      "peso_final": 150,
      "nome_configuracao": "Exportação 4KG Palmer"
    }
  ]
}
```

### configuracoes_embalamento.json
```json
{
  "version": 1,
  "created_at": "2026-05-14 10:00:00",
  "updated_at": "2026-05-14 10:00:00",
  "configuracoes": [
    {
      "id": 1,
      "nome": "Exportação 4KG Palmer",
      "faixa_peso_id": 1,
      "mapeamentos": [
        {
          "gr": 1,
          "descricao": "REFUGO",
          "produto_operacional": "Refugo"
        }
      ]
    }
  ]
}
```

### registros_lote.json
```json
{
  "version": 1,
  "created_at": "2026-05-14 10:00:00",
  "updated_at": "2026-05-14 10:00:00",
  "lotes": [
    {
      "id": 1,
      "controle": "CTRL-2026-001",
      "configuracao_embalamento_id": 1,
      "programa": "MANGO PALMER",
      "partida": "Partida 001",
      "produtor": "João Silva",
      "variedade": "Palmer",
      "classe": "Extra",
      "observacoes": "Lote de teste",
      "status": "salvo",
      "created_at": "2026-05-14 10:00:00",
      "updated_at": "2026-05-14 10:00:00"
    }
  ]
}
```

### distribuicoes_lote.json
```json
{
  "version": 1,
  "created_at": "2026-05-14 10:00:00",
  "updated_at": "2026-05-14 10:00:00",
  "distribuicoes": [
    {
      "id": 1,
      "lote_id": 1,
      "configuracao_embalamento_id": 1,
      "itens": [
        {
          "gr": 1,
          "descricao": "REFUGO",
          "faixa_peso": "50-150",
          "produto_operacional": "Refugo",
          "gramas": 100,
          "percentual": 5.2
        }
      ],
      "total_gramas": 1920.5,
      "status": "salvo",
      "created_at": "2026-05-14 10:00:00",
      "updated_at": "2026-05-14 10:00:00"
    }
  ]
}
```

---

## Fluxo de Uso Típico

1. **Etapa 1**: Criar faixas de peso
   - Exemplo: REFUGO (50-150g), 14 (150-270g), 12 (270-385g)

2. **Etapa 2**: Configurar embalamento
   - Mapear cada faixa para um Produto Operacional
   - Exemplo: Faixa 2 → "Descarte Polpa"

3. **Etapa 3**: Registrar lote
   - Criar lote com Controle + Configuração
   - Preencher opcionalmente Programa, Partida, etc

4. **Etapa 4**: Distribuição
   - Informar quantas gramas em cada faixa
   - Sistema calcula percentuais automaticamente

5. **Etapa 5**: Resultado
   - Visualizar perfil operacional agregado
   - Preparado para uso em cronoanálise/balanceamento

---

## API de Serviço

### CalbradoraService

```php
// Faixa de Peso
getFaixas(): FaixaPeso[]
getFaixasPorConfiguracao(string $nome_config): FaixaPeso[]
criarFaixa(string $desc, float $min, float $max, string $config): ?FaixaPeso
atualizarFaixa(FaixaPeso $faixa): bool
deletarFaixa(int $id): bool

// Configuração
getConfiguracoes(): ConfiguracaoEmbalamento[]
getConfiguracaoPorId(int $id): ?ConfiguracaoEmbalamento
criarConfiguracao(string $nome, int $faixa_id): ?ConfiguracaoEmbalamento
atualizarConfiguracao(ConfiguracaoEmbalamento $config): bool
deletarConfiguracao(int $id): bool

// Lote
getLotes(): RegistroLote[]
getLotePorId(int $id): ?RegistroLote
criarRegistroLote(string $controle, ...): ?RegistroLote
salvarRegistroLote(int $id): bool
deletarRegistroLote(int $id): bool

// Distribuição
getDistribuicaoPorId(int $id): ?DistribuicaoLote
criarDistribuicaoLote(int $lote_id, int $config_id): ?DistribuicaoLote
atualizarDistribuicaoLote(DistribuicaoLote $dist): bool
salvarDistribuicaoLote(int $id): bool

// Resultado
gerarResultadoOperacional(int $dist_id): array
```

---

## Segurança de Dados

- **Lock Files**: Arquivos `.lock` para evitar condições de corrida
- **Validação de Entrada**: Todos os inputs são sanitizados e validados
- **Isolamento de Namespace**: Código específico não interfere com legado
- **Sem Execução SQL**: Apenas JSON para máxima portabilidade

---

## Próximos Passos (Futuro)

1. **Integração com Cronoanálise**: Usar Resultado Operacional como input
2. **Integração com Árvore de Estrutura**: Mapeamento de produtos
3. **Integração com Balanceamento**: Usar percentuais para cálculo de pessoas
4. **OCR/Imagem**: Leitura automática de valores da calibradora
5. **Histórico**: Análise temporal de distribuições

---

## Contato e Suporte

Para dúvidas ou melhorias, consulte o arquivo `/docs/reformulacao/` com documentação adicional.

**Último Update:** 14 de Maio de 2026
