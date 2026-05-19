# Estrutura de Dados - Sistema de Balanceamento v2

## Visão Geral

O sistema foi refatorado para suportar:
- ✅ **Múltiplas linhas por setor**
- ✅ **Múltiplos postos por linha**
- ✅ **Conexões/Fluxos entre postos** (série, paralelo)
- ✅ **Propriedades de fluxo** (tipo de nó, paralelo, origem/destino)

## Arquivos de Dados (JSON)

### 1. setores.json
Armazena informações dos setores (máquinas, áreas de produção).

```json
[
    {
        "id": "setor_1234567890",
        "nome": "Máquina Grande",
        "descricao": "Máquina de processamento principal",
        "data_criacao": "2026-05-04 10:30:00",
        "linhas_ids": ["linha_abc123", "linha_def456"]
    }
]
```

**Campos:**
- `id`: Identificador único (gerado automaticamente)
- `nome`: Nome do setor
- `descricao`: Descrição opcional
- `data_criacao`: Timestamp de criação
- `linhas_ids`: Array de IDs de linhas vinculadas ao setor

---

### 2. linhas.json
Armazena as linhas de produção, com postos e conexões.

```json
[
    {
        "id": "linha_abc123",
        "nome": "Linha 1 - Processamento",
        "setor_id": "setor_1234567890",
        "descricao": "Fluxo principal de processamento",
        "postos": [
            {
                "id": "posto_xyz789",
                "nome": "Puxar fruta",
                "tipo": "node",
                "paralelo": false,
                "postos_origem": [],
                "postos_destino": ["posto_abc123"],
                "configs": [],
                "detalhes": {
                    "unidade_tipica": "Contentor",
                    "quantidade_item": 42,
                    "tempo_total": 180,
                    "tempo_por_item": 4.29,
                    "fator_correlacao": "1.0",
                    "observacao": ""
                },
                "atividades": [
                    {
                        "tipo_operacao": "ESTATICA|TRANSPORTE|MISTA",
                        "descricao": "Puxa pallet fruta in natura",
                        "unidade": "Contentor",
                        "quantidade": 42,
                        "peso_unidade": 20,
                        "tempo_total": 180,
                        "tempo_por_unidade": 4.29,
                        "distancia_m": 0,
                        "meio_transporte": "",
                        "origem": "",
                        "destino": "",
                        "elementos_operacionais": [
                            { "sequencia": 1, "tipo": "ESTATICA", "descricao": "...", "tempo": 10 }
                        ]
                    }
                ],
                "recursos": {
                    "num_pessoas": 5
                },
                "posicao": {
                    "x": 100,
                    "y": 150
                }
            }
        ],
        "conexoes": [
            {
                "id": "conexao_con123",
                "origem": "posto_xyz789",
                "destino": "posto_abc123",
                "tipo": "serie"
            },
            {
                "id": "conexao_con456",
                "origem": "posto_abc123",
                "destino": "posto_def789",
                "tipo": "paralelo"
            }
        ],
        "unidade_basica": "Contentor"
    }
]
```

**Campos da Linha:**
- `id`: Identificador único
- `nome`: Nome da linha
- `setor_id`: ID do setor pai
- `descricao`: Descrição opcional
- `postos`: Array de postos na linha
- `conexoes`: Array de conexões entre postos
- `unidade_basica`: Unidade padrão para cálculos

**Campos do Posto:**
- `id`: Identificador único
- `nome`: Nome do posto
- `tipo`: Tipo de nó (`node`, `parallel`, `merge`, `flow`)
- `paralelo`: Boolean indicando se trabalha em paralelo
- `postos_origem`: Array de IDs de postos que alimentam este
- `postos_destino`: Array de IDs de postos que este alimenta
- `configs`: Configurações customizadas
- `detalhes`: Informações detalhadas (unidade, tempo, quantidade)
- `atividades`: Array de atividades/tarefas
- `recursos`: Pessoas, equipamentos necessários
- `posicao`: Coordenadas para visualização (x, y)

**Campos da Conexão:**
- `id`: Identificador único
- `origem`: ID do posto de origem
- `destino`: ID do posto de destino
- `tipo`: `serie` (sequencial) ou `paralelo` (simultâneo)

---

## Fluxo de Navegação

```
setores.php (Criar/Editar Setores)
    ↓
linhas.php (Criar Múltiplas Linhas por Setor)
    ↓
postos.php (Criar Postos em cada Linha)
    ↓
fluxos.php (Conectar Postos com Série/Paralelo)
    ↓
index.php (Visualizar Fluxo com Drawflow)
```

---

## Funcionalidades Novas

### 1. Múltiplas Linhas por Setor
- Cada setor pode ter várias linhas de produção
- Útil para máquinas com fluxos paralelos
- Exemplo: Máquina Grande (Setor) com Linha 1, Linha 2, Linha 3

### 2. Fluxos Série/Paralelo
- **Série**: Posto A → Posto B (um após o outro)
- **Paralelo**: Posto A → Postos B e C (simultâneos)
- Similar a Bizaggi Modeler

### 3. Propriedades de Nó
- `node`: Nó padrão de processamento
- `parallel`: Nó que inicia processamento paralelo
- `merge`: Nó que une fluxos paralelos
- `flow`: Nó de conexão/redirecionamento

### 4. Salvamento de Dados
Todos os dados são salvos em JSON no diretório `/data`:
- `data/setores.json`
- `data/linhas.json`
- `data/unidades.json`
- `data/categorias_atividade.json`
- `data/tipos_item.json`

---

## Funções Auxiliares (data_store.php)

```php
// Gerar IDs únicos
$id = generate_id('posto');  // => "posto_1234567890_5678"

// Encontrar por ID
$setor = find_setor_by_id($setores, $setor_id);
$linha = find_linha_by_id($linhas, $linha_id);
$posto = find_posto_in_linha($linha, $posto_id);

// Consultas úteis
$linhas_setor = get_linhas_by_setor($linhas, $setor_id);
$postos_origem = get_postos_origem($linha, $posto_id);
$postos_destino = get_postos_destino($linha, $posto_id);

// Criar estruturas padrão
$novo_setor = create_default_setor("Nome", "Descrição");
$nova_linha = create_default_linha("Nome", $setor_id);
$novo_posto = create_default_posto("Nome");

// Gerenciar conexões
add_conexao($linha, $origem_id, $destino_id, 'serie');
remove_conexao($linha, $conexao_id);
```

---

## Exemplo de Uso: Criar Estrutura Completa

1. **Criar Setor**
   - `Máquina Grande` (setor_1)

2. **Criar Linhas**
   - `Linha 1 - Processamento` (linha_1)
   - `Linha 2 - Embalagem` (linha_2)

3. **Criar Postos na Linha 1**
   - `Puxar fruta` (paralelo)
   - `Lavar` (série)
   - `Secar` (série)
   - `Empacotar` (paralelo com Puxar)

4. **Conectar Postos**
   - Puxar → Lavar (série)
   - Lavar → Secar (série)
   - Secar → Empacotar (paralelo com Puxar)

5. **Visualizar no Drawflow**
   - index.php mostra o fluxo completo em Drawflow

---

## Migração de Dados Antigos

Se você tem dados antigos em formato diferente, use as funções de carregamento em `data_store.php` para converter:

```php
$linhas_antigas = load_json_data('linhas_backup');
$linhas_novas = [];

foreach ($linhas_antigas as $linha_antiga) {
    $nova_linha = create_default_linha($linha_antiga['nome'], 'setor_1');
    
    // Copiar postos, criando IDs novos
    foreach ($linha_antiga['postos'] as $posto_antigo) {
        $novo_posto = create_default_posto($posto_antigo['nome']);
        // Copiar campos adicionais conforme necessário
        $nova_linha['postos'][] = $novo_posto;
    }
    
    $linhas_novas[] = $nova_linha;
}

save_json_data('linhas', $linhas_novas);
```

---

## Dicas de Performance

- Use `find_*` para buscar por ID (mais rápido)
- Carregue dados uma vez com `load_json_data()` no início
- Salve com `save_json_data()` apenas quando necessário
- Para grandes volumes, considere usar banco de dados

---

## Próximos Passos

- Integração com banco de dados
- Cálculos de balanceamento automático
- Exportação de relatórios
- Sistema de permissões de usuários
- Histórico de alterações
