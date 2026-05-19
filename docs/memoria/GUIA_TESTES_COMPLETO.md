# 🧪 GUIA DE TESTES - VERIFICAÇÃO DAS MUDANÇAS

## ✅ Como Testar as Melhorias

---

## 1️⃣ TESTES - TELA DE UNIDADES

### Abrir a Página
```
Navegue para: http://localhost/unidades.php
ou use: php -S localhost:8000 no diretório public/
```

### Testes Visuais

- [ ] **Título com Badge**
  - Título exibe "📦 Gerenciamento de Unidades [N]"
  - Badge mostra número de unidades cadastradas
  - Badge tem cor azul com texto branco

- [ ] **Seção de Formulário**
  - Fundo cinza claro (#f8f9fa)
  - Bordinha sutil e sombra
  - Título "➕ Adicionar Nova Unidade" em negrito
  - Campos bem espaçados

- [ ] **Formulário**
  - Campo "Nome da Unidade" com label
  - Campo "Peso Padrão (kg)" com label
  - Campo "Observação" como textarea
  - Botão "Adicionar Unidade" em azul
  - Label com asterisco vermelho (*) para campos obrigatórios

- [ ] **Tabela**
  - Header com fundo gradiente azul
  - Colunas: Nome | Peso | Observação | Ações
  - Largura de colunas balanceada
  - Peso em badge com cor ciano
  - Botões de ação pequenos (✏️ e 🗑️)

### Testes de Funcionalidade

- [ ] **Adicionar Unidade**
  ```
  1. Digite "Test" em Nome
  2. Digite "5.5" em Peso
  3. Deixe Observação vazia
  4. Clique em "Adicionar"
  5. Verifique se aparece na tabela
  6. Verifique se badge foi atualizado
  ```

- [ ] **Mensagem de Sucesso**
  - Aparece abaixo do título
  - Tem ícone ✅
  - Fundo verde, texto verde escuro
  - Desaparece quando recarregar

- [ ] **Erro (Peso Zero)**
  ```
  1. Digite "Test 2" em Nome
  2. Digite "0" em Peso
  3. Clique Adicionar
  4. Deve aparecer mensagem de erro
  5. Mensagem: "Peso padrão deve ser maior que zero"
  ```

- [ ] **Remover Unidade**
  - Clique no botão 🗑️
  - Aparece diálogo de confirmação
  - Clique OK
  - Unidade é removida
  - Badge é atualizado

### Testes Responsivos

- [ ] **Desktop (> 1024px)**
  - Formulário em grid com múltiplas colunas
  - Tabela com todas as colunas visíveis
  - Sem scroll horizontal

- [ ] **Tablet (768px - 1024px)**
  - Formulário em 2 colunas
  - Tabela com scroll se necessário
  - Botões legíveis

- [ ] **Mobile (< 768px)**
  - Formulário em 1 coluna
  - Tabela pode ter scroll horizontal
  - Botões maiores
  - Sidebar colapsável

---

## 2️⃣ TESTES - TELA DE POSTOS

### Abrir a Página
```
Navegue para: http://localhost/postos.php
```

### Testes Visuais

- [ ] **Título com Badge**
  - Título "📍 Gerenciamento de Postos [N]"
  - Badge mostra total de postos
  - Cor e estilo consistente com página de unidades

- [ ] **Seção de Formulário**
  - Background cinza claro
  - Título "➕ Adicionar Novo Posto"
  - Dropdown para selecionar linha
  - Input para nome do posto
  - Botão "Adicionar"

- [ ] **Tabela de Postos**
  - Colunas: Linha | Nome do Posto | Ações
  - Header azul com gradiente
  - Linhas com hover (background mais claro)
  - Botões coloridos:
    - Azul (⚙️ Atividades)
    - Verde (✏️ Atualizar)
    - Vermelho (🗑️ Remover)

- [ ] **Seção de Atividades**
  - Separada com linha horizontal
  - Título "⚙️ Gerenciar Atividades do Posto"
  - Dropdowns para selecionar linha e posto
  - Espaço para iframe

### Testes de Funcionalidade

- [ ] **Adicionar Posto**
  ```
  1. Selecione uma linha no dropdown
  2. Digite nome do posto (ex: "Embalagem")
  3. Clique "Adicionar"
  4. Verifique na tabela
  5. Badge foi atualizado
  ```

- [ ] **Editar Posto**
  - Clique na linha do posto
  - Input deve permitir edição
  - Botão verde "Atualizar" funciona
  - Mudança reflete na tabela

- [ ] **Remover Posto**
  - Clique botão vermelho
  - Confirmação aparece
  - OK para remover
  - Badge diminui

- [ ] **Navegação de Atividades**
  - Selecione uma linha
  - Dropdown de postos se atualiza
  - Selecione um posto
  - Iframe carrega com formulário

### Testes de Cores

- [ ] **Cores por Ação**
  - Botão azul = informação/detalhe
  - Botão verde = confirmação/edição
  - Botão vermelho = perigo/remoção
  - Cores consistentes com página anterior

---

## 3️⃣ TESTES - TELA FLUXO DE LINHA (index.php)

### Abrir a Página
```
Navegue para: http://localhost/index.php
ou http://localhost/ (se for home)
```

### Testes Visuais

- [ ] **Header com Indicadores**
  - Título "Sistema de Balanceamento - Modelagem"
  - 4 indicadores no topo:
    - Postos: [número]
    - Tempo de Ciclo: [número] s
    - Taxa de Produção: [número] kg/min
    - Pessoas Alocadas: [número] 👥

- [ ] **Layout Principal**
  - Canvas com grid no centro
  - Painel de propriedades à direita
  - Toolbar no canto superior esquerdo
  - Sem erros no console

- [ ] **Nodes de Postos**
  - Aparecem no canvas
  - Mostram nome do posto
  - Mostram tempo de ciclo
  - Input para número de pessoas
  - Conectados em sequência

### Testes de Funcionalidade

- [ ] **Atualizar Pessoas**
  ```
  1. Clique em um node de posto
  2. Mude o número de pessoas no input
  3. Verifique se atualiza (ritmo muda)
  4. Indicador "Pessoas Alocadas" muda
  ```

- [ ] **Adicionar Novo Posto**
  - Clique botão "➕ Novo Posto"
  - Novo node aparece
  - Pode editar nome
  - Conecta aos outros

- [ ] **Limpar Tudo**
  - Clique "🗑️ Limpar Tudo"
  - Confirmação aparece
  - Canvas fica vazio

- [ ] **Definir Unidade Base**
  - Selecione unidade no dropdown
  - Clique botão azul
  - Dados são salvos
  - Nodes mostram tempo por unidade

---

## 4️⃣ TESTES GLOBAIS

### Performance

- [ ] **Tempo de Carregamento**
  - Página carrega em < 2 segundos
  - Sem lag ao clicar botões
  - Scroll suave em tabelas

- [ ] **Responsividade**
  - Redimensione janela
  - Layout se adapta corretamente
  - Sem elementos saindo da tela
  - Sem scroll horizontal indesejado

### Navegadores

- [ ] **Chrome**
  - Todos os elementos aparecem
  - Cores corretas
  - Sem erros no console

- [ ] **Firefox**
  - Layout idêntico ao Chrome
  - Botões funcionam
  - Modais aparecem corretamente

- [ ] **Safari**
  - Compatibilidade com iOS
  - Flexbox funciona
  - CSS variables funcionam

- [ ] **Edge**
  - Tudo funciona como Chrome
  - Sem problemas de renderização

### Acessibilidade

- [ ] **Navegação por Teclado**
  - Tab navega entre elementos
  - Enter ativa botões
  - Sem armadilhas de foco

- [ ] **Contraste de Cores**
  - Texto legível em todos os fundos
  - Buttons com bom contraste
  - WCAG AA compliant

- [ ] **Labels**
  - Todos os inputs têm label
  - Asterisco para obrigatórios
  - Clique em label foca input

---

## 5️⃣ TESTES DE DADOS

### Persistência

- [ ] **Unidades**
  - Adicione unidade
  - Recarregue página (F5)
  - Unidade ainda existe

- [ ] **Postos**
  - Adicione posto
  - Mude linha
  - Volte à linha
  - Posto ainda existe

- [ ] **Pessoas**
  - Digite número de pessoas
  - Recarregue página
  - Valor é mantido

### Validação

- [ ] **Campo Obrigatório**
  - Tente enviar sem preencher
  - Mensagem de erro aparece
  - Campo fica destacado

- [ ] **Número Negativo**
  - Tente digitar número negativo
  - Input não permite (type="number")
  - Valor mínimo é 0

- [ ] **Duplicação**
  - Tente adicionar unidade com mesmo nome
  - Sistema não permite
  - Mensagem de aviso

---

## 6️⃣ CHECKLIST RÁPIDO

### CSS Global (styles.css)

- [ ] Arquivo existe em `/public/styles.css`
- [ ] Arquivo tem ~400 linhas
- [ ] Variáveis CSS definidas
- [ ] Componentes principais: botões, badges, cards
- [ ] Responsividade com breakpoints

### Arquivos Refatorados

- [ ] `menu.php` usa link para styles.css
- [ ] `unidades.php` sem CSS inline
- [ ] `postos.php` sem CSS inline
- [ ] `index.php` preparado para próxima fase

### Funcionalidade

- [ ] 100% de funcionalidade mantida
- [ ] Sem quebras em fluxo de dados
- [ ] Mensagens funcionam
- [ ] Validações funcionam
- [ ] AJAX updates funcionam

---

## 7️⃣ PROBLEMAS E SOLUÇÕES

### Se layout está quebrado

**Solução 1**: Limpar cache
```
Ctrl + Shift + Delete (Chrome)
ou Cmd + Shift + Delete (Safari)
```

**Solução 2**: Verificar estilos
```
1. F12 (abrir DevTools)
2. Clique no elemento
3. Verifique se estilos estão sendo aplicados
4. Verifique se styles.css foi carregado
```

**Solução 3**: Recarregar página
```
Ctrl + F5 (força reload)
ou Cmd + Shift + R (Mac)
```

### Se cores estão estranhas

1. Verifique se menu.php inclui styles.css
2. Verifique variáveis CSS em styles.css
3. Limpe cache do navegador

### Se tabelas não aparecem

1. Verifique dados no banco de dados
2. Verifique console por erros JavaScript
3. Verifique se PHP está retornando dados

### Se responsividade não funciona

1. Verifique viewport meta tag
2. Redimensione janela do navegador
3. Use DevTools em modo responsivo (F12)

---

## 🎯 Pontos-Chave para Validar

✅ **Essencial Validar**:
1. Layout melhorado em todas as telas
2. CSS centralizado (não mais em cada arquivo)
3. Cores consistentes entre páginas
4. Botões com significado visual
5. Tabelas com melhor espaçamento
6. 100% funcionalidade mantida
7. Responsivo em todos os tamanhos
8. Sem erros no console

✅ **Bom Ter**:
1. Mensagens de feedback claras
2. Confirmação antes de deletar
3. Validação em tempo real
4. Badges para dados numéricos

---

## 📊 Teste de Aceitação

Para considerar a implementação **SUCESSO**, validar:

```
[ ] ✅ Todas as 3 telas carregam sem erros
[ ] ✅ CSS está centralizado em 1 arquivo
[ ] ✅ Layout é consistente entre telas
[ ] ✅ Botões têm cores significativas
[ ] ✅ Tabelas têm melhor espaçamento
[ ] ✅ 100% funcionalidade mantida
[ ] ✅ Responsivo (desktop, tablet, mobile)
[ ] ✅ Sem quebras visuais
[ ] ✅ Performance melhorada
[ ] ✅ Documentação está completa

Se todos estão marcados: ✅ APROVADO!
```

---

## 📝 Como Relatar Problemas

Se encontrar algo que não funciona:

1. **Descreva o problema**
   - Qual tela (unidades, postos, index)
   - Qual ação levou ao problema
   - O que você esperava vs o que aconteceu

2. **Forneça contexto**
   - Navegador e versão
   - Tamanho da tela
   - Sistema operacional

3. **Inclua screenshots**
   - Do problema
   - Do console (F12)
   - Da URL

---

**Duração total de testes**: ~30 minutos

Após completar todos os testes, você terá validado com sucesso todas as mudanças implementadas! ✅
