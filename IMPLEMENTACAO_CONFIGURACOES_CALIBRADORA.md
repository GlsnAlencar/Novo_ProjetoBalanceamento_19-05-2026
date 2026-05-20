# Implementação de Gerenciamento de Configurações de Calibradora

## Resumo das Alterações

Foi adicionada ao módulo "Calibradora" a funcionalidade de **cadastro e gerenciamento de configurações/programas de classificação** com suas respectivas faixas de peso.

## O que foi implementado

### 1. **Models (Modelos de dados)**
- `ConfiguracaoCalibradora.php` - Representa uma configuração/programa de classificação
- `ConfiguracaoCalbradoraFaixa.php` - Representa uma faixa de peso dentro de uma configuração

### 2. **Repositories (Persistência de dados)**
- `ConfiguracaoCalbradoraRepository.php` - Gerencia a persistência de configurações
- `ConfiguracaoCalbradoraFaixaRepository.php` - Gerencia a persistência de faixas

### 3. **Service (Lógica de negócio)**
- Adicionados métodos ao `CalbradoraService.php` para:
  - Criar, atualizar, deletar configurações
  - Criar, atualizar, deletar faixas
  - Validar sobreposição de faixas
  - Atualizar sequências de faixas

### 4. **Controller (Processamento de requisições)**
- Adicionados casos ao `CalbradoraController.php` para processar:
  - `criar_configuracao_calibradora`
  - `atualizar_configuracao_calibradora`
  - `deletar_configuracao_calibradora`
  - `obter_configuracoes_calibradora`
  - `criar_faixa_configuracao`
  - `atualizar_faixa_configuracao`
  - `deletar_faixa_configuracao`
  - `obter_faixas_configuracao`
  - `atualizar_sequencias_faixas`

### 5. **View (Interface)**
- `etapa0_configuracoes.php` - Tela completa para gerenciar configurações e suas faixas

## Estrutura da Tela

### Seção 1: Minhas Configurações
- Exibe todas as configurações cadastradas em cards
- Permite criar nova configuração com:
  - Nome (obrigatório)
  - Descrição (opcional)
  - Status (Ativo/Inativo)

### Seção 2: Faixas de Peso da Configuração
Após selecionar uma configuração, permite:
- **Visualizar faixas** em tabela com colunas:
  - Gr. (Sequência)
  - Descripción
  - Peso Inicial
  - Peso Final
  - Ações (Editar/Deletar)

- **Adicionar nova faixa** com:
  - Sequência automática (ou manual)
  - Descripción
  - Peso Inicial
  - Peso Final

## Validações Implementadas

✓ **Configurações:**
- Nome é obrigatório
- Evita duplicação de nomes

✓ **Faixas:**
- Descripción é obrigatória
- Peso Inicial e Peso Final devem ser numéricos
- Peso Inicial < Peso Final
- **NÃO permite sobreposição de faixas** na mesma configuração
- Sequência define ordem operacional

## Estrutura de Persistência

Os dados são armazenados em JSON:

**Arquivo:** `data/reformulacao/calibradora/configuracoes_calibradora.json`
```json
{
  "version": 1,
  "created_at": "2026-05-20 10:30:00",
  "updated_at": "2026-05-20 10:35:00",
  "configuracoes": [
    {
      "id": 1,
      "nome": "Exportação",
      "descricao": "Programa para frutas de exportação",
      "ativo": true,
      "created_at": "2026-05-20 10:30:00",
      "updated_at": "2026-05-20 10:30:00"
    }
  ]
}
```

**Arquivo:** `data/reformulacao/calibradora/configuracoes_calibradora_faixas.json`
```json
{
  "version": 1,
  "created_at": "2026-05-20 10:30:00",
  "updated_at": "2026-05-20 10:35:00",
  "faixas": [
    {
      "id": 1,
      "configuracao_id": 1,
      "sequencia_grupo": 1,
      "descricao": "REFUGO",
      "peso_inicial": 50,
      "peso_final": 150,
      "created_at": "2026-05-20 10:31:00",
      "updated_at": "2026-05-20 10:31:00"
    }
  ]
}
```

## Acesso no Menu

A nova funcionalidade está acessível em:
**Reformulação → Calibradora V2 → 0. Configurações de Programas**

## Integridade do Sistema

✓ **Totalmente isolado:**
- Não modifica outros módulos
- Não altera fluxo/BPM
- Não afeta balanceamento
- Não interfere em cronoanálise
- Não muda banco de dados de outros módulos

✓ **Compatibilidade:**
- Mantém padrão visual do projeto
- Integra-se com a arquitetura MVC existente
- Usa os mesmos patterns de persistência

## Uso Futuro

Esta estrutura foi implementada para ser utilizada posteriormente por:
- Execução da calibradora
- Leitura/importação automática da máquina
- Integração futura com balanceamento
- Integração futura com cronoanálise

## Exemplo de Uso

1. Acesse: "Calibradora V2 → Configurações de Programas"
2. Clique em "Criar Nova Configuração"
3. Informe:
   - Nome: "Exportação"
   - Descrição: "Programa para frutas de exportação"
   - Status: Ativo
4. Clique na configuração criada para gerenciar faixas
5. Adicione faixas com os pesos desejados

## Funcionalidades Disponíveis

- ✓ Criar múltiplas configurações
- ✓ Editar configurações
- ✓ Deletar configurações (e todas suas faixas)
- ✓ Adicionar faixas às configurações
- ✓ Editar faixas
- ✓ Deletar faixas
- ✓ Alterar sequência das faixas
- ✓ Validação automática de sobreposição
- ✓ Status de ativo/inativo para configurações
- ✓ Descrição opcional para configurações

---

**Data de implementação:** 20 de maio de 2026
**Módulo afetado:** Calibradora (exclusivamente)
**Status:** Pronto para uso
