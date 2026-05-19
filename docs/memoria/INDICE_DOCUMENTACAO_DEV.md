# 📚 ÍNDICE CENTRAL DE DOCUMENTAÇÃO DE DESENVOLVIMENTO

> **Consulte aqui para encontrar a resposta que precisa**

---

## 🎯 PARA INICIANTES (Dev Júnior)

### "Preciso entender como tudo funciona"
👉 Leia nesta ordem:
1. [GUIA_DESENVOLVIMENTO.md](GUIA_DESENVOLVIMENTO.md) - Seção "VISÃO GERAL DO PROJETO"
2. [ARQUITETURA_NAVEGACAO.md](ARQUITETURA_NAVEGACAO.md) - Todo o documento
3. [QUICK_REFERENCE.md](QUICK_REFERENCE.md) - Cole na parede!

### "Estou fazendo uma alteração"
👉 Checklist:
1. Leia [CODE_REVIEW_CHECKLIST.md](CODE_REVIEW_CHECKLIST.md) - PHASE 1 a 3
2. Faça seus testes - PHASE 7
3. Chame Dev Senior para revisar

### "Estou com erro"
👉 Procure em:
1. [GUIA_DESENVOLVIMENTO.md](GUIA_DESENVOLVIMENTO.md) - Seção "REGRAS DE INTEGRIDADE"
2. [QUICK_REFERENCE.md](QUICK_REFERENCE.md) - Seção "TOP 5 ERROS"
3. [ARQUITETURA_NAVEGACAO.md](ARQUITETURA_NAVEGACAO.md) - Seção "ERROS MAIS FREQUENTES"

---

## 🔧 PARA EXPERIENCED (Dev Senior)

### "Preciso revisar código de um dev júnior"
👉 Use:
1. [CODE_REVIEW_CHECKLIST.md](CODE_REVIEW_CHECKLIST.md) - Todo documento
2. Tempo estimado: 90 minutos por PR

### "Preciso adicionar uma nova feature"
👉 Siga:
1. [GUIA_DESENVOLVIMENTO.md](GUIA_DESENVOLVIMENTO.md) - Seções "CONVENÇÕES" e "CÁLCULOS"
2. [ARQUITETURA_NAVEGACAO.md](ARQUITETURA_NAVEGACAO.md) - Seção "FLUXO COMPLETO"
3. Documente em [GUIA_DESENVOLVIMENTO.md](GUIA_DESENVOLVIMENTO.md) - Seção "ADIÇÕES/ALTERAÇÕES"

### "Preciso refatorar data_store.php"
👉 Consulte:
1. [GUIA_DESENVOLVIMENTO.md](GUIA_DESENVOLVIMENTO.md) - Seção "FUNÇÕES CRÍTICAS"
2. [ARQUITETURA_NAVEGACAO.md](ARQUITETURA_NAVEGACAO.md) - Seção "TESTE MANUAL"
3. Revalidar todos os links ANTES de mergear

---

## 🚨 DECISÕES ARQUITETURAIS CRÍTICAS

### ❌ Coisa que NÃO MUDA (sem aprovação do arquiteto)

```
┌────────────────────────────────────────────────────────┐
│ 1. Uso de linha_id vs setor_id                         │
│    - linha_id = ID ÚNICO da linha                      │
│    - setor_id = ID ÚNICO do setor                      │
│    - NUNCA confundir esses dois!                       │
│                                                         │
│ 2. Estrutura JSON em data/linhas.json                  │
│    - Não retirar campos obrigatórios                   │
│    - Adicionar campos: OK (com discussão)             │
│    - Remover campos: NÃO (quebra compatibilidade)     │
│                                                         │
│ 3. Post-index é sempre 0-based                        │
│    - Índice do array, não ID sequencial               │
│    - Após unset(), SEMPRE reindexar com array_values()│
│                                                         │
│ 4. Padrão URL: ?parametro=valor&outro=valor          │
│    - Query string, não POST por padrão                │
│    - Sempre urlencode() antes                         │
│                                                         │
│ 5. Segurança: htmlspecialchars() TODO ECHO            │
│    - Escape sempre antes de exibir                    │
│    - Vale para JSON output também                     │
│                                                         │
│ 6. Persistência: save_json_data() após qualquer op.  │
│    - Sem chamada = sem persistência                   │
│    - Validar JSON antes de salvar                     │
└────────────────────────────────────────────────────────┘
```

### ✅ Coisa que PODE mudar (com cuidado)

```
┌────────────────────────────────────────────────────────┐
│ • Estilos CSS                                           │
│   → Não quebra funcionalidade                          │
│   → Sempre testar responsividade                       │
│                                                         │
│ • Textos e labels                                       │
│   → Não quebra lógica                                  │
│   → Sempre manter comentários                         │
│                                                         │
│ • Novos campos em JSON                                 │
│   → Adicione com valor default                        │
│   → Documente em GUIA_DESENVOLVIMENTO.md              │
│                                                         │
│ • Novos arquivos .php                                  │
│   → Siga convenções de nomes                          │
│   → Documente em ARQUITETURA_NAVEGACAO.md             │
│                                                         │
│ • Cálculos (com MUITO cuidado!)                        │
│   → Sempre teste manualmente                          │
│   → Documente a fórmula                               │
│   → Avise outros devs                                 │
└────────────────────────────────────────────────────────┘
```

---

## 📍 MAPA MENTAL: O QUE CADA ARQUIVO FAZ

```
┌─ FRONTEND (Usuário vê)
│
├─ index.php
│  └─ Fluxo visual com Drawflow
│     ├─ Cria/remove/modifica postos graficamente
│     ├─ Recebe: setor_id, linha_id
│     └─ Chama: atividades_posto.php, recursos.php, fluxos.php
│
├─ postos.php
│  └─ Gerencia postos por setor
│     ├─ Adiciona/remove/edita nomes de postos
│     ├─ Recebe: setor_id
│     └─ Chama: atividades_posto.php
│
├─ atividades_posto.php ⭐ CRÍTICO
│  └─ Edita atividades de um posto
│     ├─ Recebe: post (index), linha (id), back (page)
│     ├─ Cálculos: tempo/unidade, tempo/peso
│     └─ Valida: linha_id deve ser LINHA, não setor!
│
├─ recursos.php
│  └─ Define número de pessoas
│     ├─ Recebe: linha (id), post (index), back (page)
│     └─ Cálculo: ritmo = tempo_contentor / num_pessoas
│
├─ fluxos.php
│  └─ Conexões entre postos
│     ├─ Recebe: linha_id
│     └─ Operações: série, paralelo
│
├─ setores.php
│  └─ Cria/remove setores
│     └─ Sem parâmetros
│
├─ unidades.php
│  └─ Cadastra unidades de medida
│     └─ IMPORTANTE: Adicionar "Contentor" com peso!
│
├─ menu.php
│  └─ Menu lateral compartilhado
│     └─ Include em todos os arquivos
│
└─ styles.css
   └─ Estilos globais + media queries


┌─ BACKEND (Sistema trabalha)
│
├─ data_store.php ⭐ CRÍTICO
│  └─ Funções de dados
│     ├─ load_json_data() - carrega JSON
│     ├─ save_json_data() - salva JSON
│     ├─ find_linha_by_id() - busca linha
│     ├─ find_setor_by_id() - busca setor
│     └─ find_posto_in_linha() - busca posto
│
├─ security.php
│  └─ Validações de segurança
│     └─ Include quando necessário
│
└─ data/ (Arquivos JSON)
   ├─ linhas.json - Postos + Atividades
   ├─ setores.json - Setores
   ├─ unidades.json - Unidades de medida
   ├─ transporte.json - Info de transporte
   └─ ⚠️ Sempre fazer backup antes de alterar!
```

---

## 🔄 FLUXO DE TRABALHO: CI/CD MENTAL

```
┌─ DESENVOLVIMENTO
│
├─ Receber tarefa
│  └─ Ler especificação em CODE_REVIEW_CHECKLIST.md PHASE 1-3
│
├─ Codificar
│  └─ Seguir convenções em GUIA_DESENVOLVIMENTO.md
│
├─ Testar localmente
│  └─ Fase 7-10 de CODE_REVIEW_CHECKLIST.md
│
├─ Fazer backup
│  └─ cp data/*.json data/backup_YYYYMMDD_HHMMSS/
│
├─ Commit com mensagem clara
│  └─ "Corrigir: atividades não carregavam (issue #X)"
│
├─ Push para repo
│  └─ Abrir PR com descrição
│
├─ Code Review (Dev Senior)
│  └─ Usar CODE_REVIEW_CHECKLIST.md completo (90 min)
│
└─ Merge e Deploy
   └─ Verificar se JSON está válido em produção
```

---

## 📊 TABELA: RESPONSABILIDADES

| Dev Júnior | Dev Senior | Arquiteto |
|-----------|-----------|-----------|
| Codificar features simples | Revisar código | Decidir arquitetura |
| Testar funcionalidades | Aprovar/rejeitar PRs | Resolver conflitos |
| Seguir convenções | Treinar juniors | Documentar |
| Ler documentação | Refatorar | Autorizações |
| Avisar bugs | Bugs críticos | Mudanças estruturais |

---

## 🎓 LEARNING PATH: Do Júnior ao Senior

### Semana 1: Entender o Sistema
- [ ] Ler GUIA_DESENVOLVIMENTO.md inteiro
- [ ] Ler ARQUITETURA_NAVEGACAO.md inteiro
- [ ] Ler data/linhas.json e entender estrutura
- [ ] Fazer um teste manual simples (adicionar atividade)

### Semana 2: Fazer Primeira Alteração
- [ ] Alterar um texto de label em postos.php
- [ ] Pedir para Dev Senior revisar
- [ ] Aprender com feedback

### Semana 3: Independência
- [ ] Fazer alteração em uma tela (ex: novo campo em recurso)
- [ ] Auto-revisar com CODE_REVIEW_CHECKLIST
- [ ] Pedir review do Senior

### Semana 4: Confiança
- [ ] Fazer alteração pequena sem supervisão
- [ ] Dev Senior revisa depois (não antes)
- [ ] Aprender a revisar próprio código

### Mês 2+: Dev Pleno
- [ ] Revisar código de outro júnior
- [ ] Sugerir refatorações
- [ ] Ensinar ao próximo júnior

---

## ⚡ SNIPPETS MAIS USADOS

### Receber e validar
```php
$linha_id = $_GET['linha'] ?? null;
if (!$linha_id) die('❌ Erro');
$linha = find_linha_by_id($linhas, $linha_id);
if (!$linha) die('❌ Não encontrado');
```

### Remover e reindexar
```php
unset($array[$idx]);
$array = array_values($array);
save_json_data('linhas', $linhas_json);
```

### Exibir com escape
```php
echo '<a href="page.php?id=' . urlencode($id) . '">';
echo htmlspecialchars($name);
```

### Cálculo com arredondamento
```php
$tempo_por_unidade = round($total / $qtd, 2);
$tempo_por_peso = round($tempo_por_unidade / $peso, 4);
```

---

## 📞 MATRIZ DE ESCALAÇÃO

| Problema | Primeira Ação | Escalação |
|----------|---------------|-----------|
| Erro em feature minha | Checar [QUICK_REFERENCE.md](QUICK_REFERENCE.md) | Dev Senior |
| Sistema quebrou | Restaurar backup | Arquiteto |
| Dúvida de parâmetro | Consultar [ARQUITETURA_NAVEGACAO.md](ARQUITETURA_NAVEGACAO.md) | Dev Senior |
| Cálculo errado | Validar manualmente | Arquiteto |
| JSON corrompido | Restaurar backup | Arquiteto |
| Feature muito grande | Dividir em tasks | Dev Senior |

---

## 🏆 PRINCÍPIOS DO PROJETO

```
1. CONSISTÊNCIA
   - Sempre usar mesmos nomes de variáveis
   - Sempre usar mesmos padrões de URL
   - Sempre validar de mesma forma

2. CLAREZA
   - Código legível > código inteligente
   - Comentários explicam o "por quê"
   - Nomes descritivos de variáveis

3. SEGURANÇA
   - Validar SEMPRE entrada
   - Escapar SEMPRE saída
   - Nunca confiar em dados do usuário

4. INTEGRIDADE
   - Dados nunca se perdem
   - Cálculos sempre corretos
   - JSON sempre válido

5. DOCUMENTAÇÃO
   - Código auto-documentado
   - Documentação atualizada
   - Exemplos funcionais
```

---

## 🔗 ARQUIVOS DESTE PROJETO

| Arquivo | Propósito | Frequência |
|---------|-----------|-----------|
| [GUIA_DESENVOLVIMENTO.md](GUIA_DESENVOLVIMENTO.md) | Referência completa | Sempre |
| [QUICK_REFERENCE.md](QUICK_REFERENCE.md) | Consulta rápida | Diário |
| [ARQUITETURA_NAVEGACAO.md](ARQUITETURA_NAVEGACAO.md) | Fluxos e parâmetros | Semanal |
| [CODE_REVIEW_CHECKLIST.md](CODE_REVIEW_CHECKLIST.md) | Validação antes de merge | Sempre |
| [INDICE_DOCUMENTACAO.md](INDICE_DOCUMENTACAO.md) | Este arquivo | Semanal |
| [README.md](README.md) | Setup inicial | Primeira vez |
| [ESTRUTURA_DADOS_V2.md](ESTRUTURA_DADOS_V2.md) | Dados detalhados | Ocasional |

---

## ✅ NEXT STEPS

### Para Dev Júnior
1. [ ] Imprimir [QUICK_REFERENCE.md](QUICK_REFERENCE.md)
2. [ ] Colar na parede perto do monitor
3. [ ] Ler [GUIA_DESENVOLVIMENTO.md](GUIA_DESENVOLVIMENTO.md) hoje
4. [ ] Fazer primeiro teste manual amanhã

### Para Dev Senior
1. [ ] Testar sistema completo
2. [ ] Revisar se todos os links funcionam
3. [ ] Validar JSON em data/
4. [ ] Briefing com dev júnior segunda-feira

### Para Arquiteto
1. [ ] Validar arquitetura está documentada
2. [ ] Verificar se convenções são claras
3. [ ] Atualizar se houver mudanças

---

**Criado:** 05/05/2026
**Versão:** 1.0 - ESTÁVEL
**Responsável:** Arquitetura

Use este documento como ponto de partida para qualquer dúvida! 🚀

