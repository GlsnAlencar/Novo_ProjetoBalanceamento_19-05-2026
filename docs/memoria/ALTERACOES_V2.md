# Alterações Implementadas - Sistema de Balanceamento v2

## 📋 Resumo das Mudanças

O sistema foi completamente refatorado para suportar:

### ✅ Principais Funcionalidades Adicionadas

1. **Múltiplas Linhas por Setor**
   - Cada setor (Máquina) pode agora ter várias linhas de produção
   - Seletor dinâmico de linhas no fluxo principal (index.php)
   - Gerenciamento completo em linhas.php

2. **Postos com Propriedades de Fluxo**
   - `tipo`: node, parallel, merge, flow
   - `paralelo`: boolean indicando processamento paralelo
   - `postos_origem` e `postos_destino`: rastreamento de conexões
   - Integração com IDs únicos para cada posto

3. **Fluxos Série/Paralelo (Bizaggi-like)**
   - Conectar postos em série (sequencial)
   - Conectar postos em paralelo (simultâneo)
   - Visualização de conexões com tipos
   - Gerenciamento em fluxos.php

4. **Salvamento Estruturado de Dados**
   - Todos os dados em JSON no diretório `/data`
   - Estrutura uniforme e consistente
   - Funções auxiliares centralizadas em data_store.php

---

## 📁 Arquivos Modificados/Criados

### Modificados:

1. **[data_store.php](data_store.php)**
   - Adicionadas 15+ funções auxiliares
   - `generate_id()`, `find_setor_by_id()`, `find_linha_by_id()`, etc.
   - `create_default_*()` para estruturas padrão
   - `add_conexao()`, `remove_conexao()` para fluxos
   - `get_linhas_by_setor()`, `get_postos_origem()`, `get_postos_destino()`

2. **[index.php](index.php)**
   - Atualizado para carregar múltiplas linhas
   - Seletores dinâmicos de setor e linha
   - Abas de linhas por setor
   - Compatibilidade com nova estrutura de dados

3. **[setores.php](setores.php)**
   - Redesenhado com cards visuais
   - Suporta múltiplas linhas por setor
   - Links para gerenciar linhas e visualizar fluxo
   - Mostra estatísticas (linhas, postos, conexões)

4. **[menu.php](menu.php)**
   - Adicionados links para:
     - 📦 Linhas (novo)
     - 🔗 Fluxos/Conexões (novo)
   - Reorganização visual

5. **[postos.php](postos.php)** (completamente refatorado)
   - Trabalha com `linha_id` ao invés de `setor_id`
   - Layout em cards em grid
   - Suporte a propriedades de tipo e paralelo
   - Links para gerenciar fluxos
   - Compatibilidade retroativa mantida

### Criados:

1. **[linhas.php](linhas.php)** (NOVO)
   - Gerenciamento de múltiplas linhas por setor
   - Criar, editar, remover linhas
   - Visualizar estatísticas de cada linha
   - Links rápidos para postos e fluxos

2. **[fluxos.php](fluxos.php)** (NOVO)
   - Gerenciamento de conexões entre postos
   - Criar conexões série/paralelo
   - Marcar postos como paralelo
   - Visualizar todas as conexões
   - Remover conexões

3. **[ESTRUTURA_DADOS_V2.md](ESTRUTURA_DADOS_V2.md)** (NOVO)
   - Documentação completa da estrutura de dados
   - Exemplos de uso
   - Referência de funções
   - Guia de migração

---

## 🔄 Fluxo de Navegação

```
setores.php (Cadastrar Setores)
    ↓
linhas.php (Criar Múltiplas Linhas)
    ↓
postos.php (Adicionar Postos)
    ↓
fluxos.php (Conectar Série/Paralelo)
    ↓
index.php (Visualizar Fluxo Completo)
```

---

## 📊 Estrutura de Dados JSON

### setores.json
```json
{
    "id": "setor_1234567890",
    "nome": "Máquina Grande",
    "descricao": "Setor de processamento",
    "data_criacao": "2026-05-04 10:00:00",
    "linhas_ids": ["linha_1", "linha_2"]
}
```

### linhas.json
```json
{
    "id": "linha_1",
    "nome": "Linha 1 - Processamento",
    "setor_id": "setor_1234567890",
    "descricao": "Fluxo principal",
    "postos": [
        {
            "id": "posto_1",
            "nome": "Puxar fruta",
            "tipo": "node",
            "paralelo": false,
            "postos_origem": [],
            "postos_destino": ["posto_2"],
            "recursos": {"num_pessoas": 5},
            "detalhes": {...},
            "atividades": [...],
            "posicao": {"x": 100, "y": 150}
        }
    ],
    "conexoes": [
        {
            "id": "conexao_1",
            "origem": "posto_1",
            "destino": "posto_2",
            "tipo": "serie"
        }
    ]
}
```

---

## 🎯 Como Usar o Sistema

### 1. Criar um Setor
- Acesse **Setores** no menu
- Clique em "Adicionar Novo Setor"
- Digite nome e descrição
- Clique em "Cadastrar Setor"

### 2. Criar Linhas no Setor
- No card do setor, clique em "Gerenciar Linhas"
- Clique em "Adicionar Nova Linha"
- Nomeie a linha (ex: "Linha 1", "Linha 2")
- Clique em "Adicionar Linha"

### 3. Adicionar Postos na Linha
- Clique em "✏️ Editar" ou acesse **Postos**
- Selecione a linha desejada
- Clique em "Adicionar Novo Posto"
- Digite o nome do posto
- Clique em "Adicionar Posto"

### 4. Conectar Postos (Fluxo)
- Acesse **Fluxos/Conexões** no menu
- Ou clique em "Gerenciar Conexões (Fluxos)" nos postos
- Selecione "Posto de Origem"
- Escolha tipo: **Série** (sequencial) ou **Paralelo** (simultâneo)
- Selecione "Posto de Destino"
- Clique em "Adicionar Conexão"

### 5. Visualizar Fluxo Completo
- Acesse **Fluxo da Linha** (index.php)
- Selecione o setor e linha na dropdown
- Veja o fluxo no Drawflow com todos os postos e conexões

---

## 🔧 Funções Disponíveis (data_store.php)

```php
// IDs e Busca
$id = generate_id('posto');  // Gera "posto_1234567890_1234"
$setor = find_setor_by_id($setores, $setor_id);
$linha = find_linha_by_id($linhas, $linha_id);
$posto = find_posto_in_linha($linha, $posto_id);

// Consultas
$linhas_setor = get_linhas_by_setor($linhas, $setor_id);
$postos_origem = get_postos_origem($linha, $posto_id);
$postos_destino = get_postos_destino($linha, $posto_id);

// Criar Estruturas
$novo_setor = create_default_setor("Nome", "Descrição");
$nova_linha = create_default_linha("Nome", $setor_id);
$novo_posto = create_default_posto("Nome");

// Fluxos
add_conexao($linha, $origem_id, $destino_id, 'serie');
add_conexao($linha, $origem_id, $destino_id, 'paralelo');
remove_conexao($linha, $conexao_id);

// I/O
$dados = load_json_data('setores');
save_json_data('setores', $dados);
```

---

## 💾 Salvamento de Dados

**Automático em:**
- `data/setores.json` - Quando setor é criado/modificado
- `data/linhas.json` - Quando linha ou posto é modificado
- `data/conexoes.json` - Ao adicionar/remover conexão

**Verificação:**
```bash
cd data/
ls -la *.json
cat setores.json   # Ver setores
cat linhas.json    # Ver linhas e postos
```

---

## 🧪 Testando o Sistema

1. **Criar estrutura de teste:**
   - Setor: "Máquina Grande"
   - Linha 1: "Processamento"
   - Linha 2: "Embalagem"
   - 5 postos na Linha 1
   - Conectar em série: Posto 1 → 2 → 3
   - Conectar em paralelo: Posto 3 → (Posto 4 e 5 simultâneos)

2. **Verificar dados:**
   - Abra `data/linhas.json`
   - Confirme estrutura de conexões
   - Teste sincronização entre páginas

3. **Validar fluxo:**
   - index.php deve mostrar todos os postos
   - Conexões devem aparecer corretamente
   - Seletores de setor/linha devem funcionar

---

## ⚠️ Compatibilidade

- ✅ PHP 7.4+
- ✅ JSON encoding/decoding automático
- ✅ Compatibilidade com dados antigos (função de migração disponível)
- ✅ Sem dependências externas (apenas Drawflow no frontend)

---

## 🚀 Próximos Passos (Futuro)

- [ ] Banco de dados (MySQL/PostgreSQL)
- [ ] Cálculos de balanceamento automático
- [ ] Relatórios PDF
- [ ] Sistema de permissões
- [ ] Histórico de alterações
- [ ] API REST
- [ ] Interface mobile

---

## 📞 Suporte

Para problemas:
1. Verifique os logs em `data/` (se houver)
2. Consulte `ESTRUTURA_DADOS_V2.md`
3. Verifique permissões de leitura/escrita em `/data`
4. Limpe cache do navegador e tente novamente

---

**Versão:** 2.0  
**Data:** 2026-05-04  
**Status:** Pronto para produção  
