<?php
/**
 * CALIBRADORA - Estilos Globais + Componentes
 * 
 * Padrão visual inspirado em Cronoanálise
 * Com suporte a dropdowns pesquisáveis
 */
?>

<style>
/* ================================================================
   VARIÁVEIS E RESET
   ================================================================ */

:root {
    --primary: #0b7bec;
    --primary-dark: #0759b4;
    --secondary: #17a2b8;
    --success: #22a846;
    --danger: #dc3545;
    --warning: #ffc107;
    --light-bg: #f5f7fa;
    --border-color: #cfd9e6;
    --text-color: #152034;
    --text-muted: #78838f;
    --box-shadow: 0 2px 10px rgba(20, 35, 55, 0.06);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: var(--light-bg);
    color: var(--text-color);
    line-height: 1.6;
}

/* ================================================================
   CONTAINERS E LAYOUT
   ================================================================ */

.calibradora-container {
    max-width: 1540px;
    margin: 0 auto;
    padding: 20px;
}

.calibradora-page {
    background: var(--light-bg);
    min-height: 100vh;
}

/* ================================================================
   HEADER / BREADCRUMB
   ================================================================ */

.calibradora-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    gap: 20px;
    flex-wrap: wrap;
}

.calibradora-header h1 {
    font-size: 28px;
    font-weight: 800;
    color: var(--text-color);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.breadcrumb-nav {
    font-size: 14px;
    color: var(--text-muted);
    margin-bottom: 16px;
}

.breadcrumb-nav a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
    transition: color 0.3s;
}

.breadcrumb-nav a:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

/* ================================================================
   CARDS E SEÇÕES
   ================================================================ */

.card {
    background: #fff;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    box-shadow: var(--box-shadow);
    margin-bottom: 20px;
    overflow: hidden;
}

.card-header {
    background: linear-gradient(90deg, var(--primary), var(--primary-dark));
    color: #fff;
    padding: 16px 20px;
    font-size: 16px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 12px;
}

.card-body {
    padding: 20px;
}

.card-footer {
    background: #f9fafb;
    border-top: 1px solid var(--border-color);
    padding: 16px 20px;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

/* ================================================================
   FORMULÁRIOS
   ================================================================ */

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
}

.form-row.row-2 {
    grid-template-columns: repeat(2, 1fr);
}

.form-row.row-3 {
    grid-template-columns: repeat(3, 1fr);
}

.form-row.row-4 {
    grid-template-columns: repeat(4, 1fr);
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 800;
    color: var(--text-color);
    letter-spacing: 0.3px;
}

.form-label .required {
    color: var(--danger);
    margin-left: 4px;
}

.form-control {
    width: 100%;
    height: 42px;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    background: #fff;
    color: var(--text-color);
    padding: 8px 12px;
    font-size: 14px;
    transition: border-color 0.3s, box-shadow 0.3s;
}

.form-control:focus {
    outline: 0;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(11, 123, 236, 0.12);
}

.form-control:disabled {
    background: #f5f7fa;
    color: var(--text-muted);
    cursor: not-allowed;
}

.form-control-lg {
    height: auto;
    min-height: 80px;
    resize: vertical;
}

/* ================================================================
   SELECT / DROPDOWN PESQUISÁVEL
   ================================================================ */

.select-wrapper {
    position: relative;
}

.select-search {
    width: 100%;
    height: 42px;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    background: #fff;
    padding: 8px 12px;
    font-size: 14px;
    color: var(--text-color);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: border-color 0.3s, box-shadow 0.3s;
}

.select-search:hover {
    border-color: var(--primary);
}

.select-search.active {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(11, 123, 236, 0.12);
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
}

.select-search-input {
    flex: 1;
    border: none;
    background: transparent;
    font-size: 14px;
    color: var(--text-color);
    padding: 0 8px;
}

.select-search-input:focus {
    outline: none;
}

.select-search-input::placeholder {
    color: var(--text-muted);
}

.select-arrow {
    color: var(--text-muted);
    font-size: 12px;
    pointer-events: none;
    transition: transform 0.3s;
}

.select-search.active .select-arrow {
    transform: rotate(180deg);
}

.select-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid var(--border-color);
    border-top: none;
    border-bottom-left-radius: 5px;
    border-bottom-right-radius: 5px;
    max-height: 300px;
    overflow-y: auto;
    z-index: 100;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    display: none;
}

.select-dropdown.active {
    display: block;
}

.select-option {
    padding: 10px 12px;
    cursor: pointer;
    font-size: 14px;
    color: var(--text-color);
    display: flex;
    align-items: center;
    gap: 8px;
    transition: background-color 0.2s;
    border-bottom: 1px solid #f0f1f3;
}

.select-option:last-child {
    border-bottom: none;
}

.select-option:hover {
    background: var(--light-bg);
}

.select-option.selected {
    background: rgba(11, 123, 236, 0.1);
    color: var(--primary);
    font-weight: 600;
}

.select-option.selected::before {
    content: "✓";
    font-weight: 800;
}

.select-option-text {
    flex: 1;
}

.select-option-sub {
    font-size: 12px;
    color: var(--text-muted);
}

.select-empty {
    padding: 16px 12px;
    text-align: center;
    color: var(--text-muted);
    font-size: 13px;
}

/* ================================================================
   TABELAS
   ================================================================ */

.table-wrapper {
    overflow-x: auto;
    border-radius: 8px;
}

.table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13px;
}

.table th {
    background: linear-gradient(90deg, var(--primary), var(--primary-dark));
    color: #fff;
    padding: 12px;
    text-align: left;
    font-weight: 800;
    white-space: nowrap;
}

.table td {
    padding: 12px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-color);
}

.table tbody tr {
    transition: background-color 0.2s;
}

.table tbody tr:hover {
    background: var(--light-bg);
}

.table tbody tr:last-child td {
    border-bottom: none;
}

.table-empty {
    padding: 40px 20px;
    text-align: center;
    color: var(--text-muted);
    font-size: 14px;
}

.table-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-start;
}

/* ================================================================
   BOTÕES
   ================================================================ */

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 40px;
    min-width: 120px;
    padding: 0 20px;
    border: none;
    border-radius: 5px;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    white-space: nowrap;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-primary {
    background: var(--primary);
    color: #fff;
}

.btn-primary:hover:not(:disabled) {
    background: var(--primary-dark);
    box-shadow: 0 4px 12px rgba(11, 123, 236, 0.3);
}

.btn-success {
    background: var(--success);
    color: #fff;
}

.btn-success:hover:not(:disabled) {
    background: #1a8c3a;
}

.btn-danger {
    background: var(--danger);
    color: #fff;
}

.btn-danger:hover:not(:disabled) {
    background: #bb2d3b;
}

.btn-secondary {
    background: #6c7682;
    color: #fff;
}

.btn-secondary:hover:not(:disabled) {
    background: #5a6268;
}

.btn-sm {
    min-height: 32px;
    min-width: auto;
    padding: 0 12px;
    font-size: 12px;
}

.btn-block {
    width: 100%;
}

/* ================================================================
   ALERTAS / FEEDBACK
   ================================================================ */

.alert {
    padding: 12px 16px;
    border-radius: 5px;
    margin-bottom: 16px;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-success {
    background: #d1e7dd;
    color: #0f5132;
    border: 1px solid #a3cfbb;
}

.alert-error {
    background: #f8d7da;
    color: #842029;
    border: 1px solid #f1aeb5;
}

.alert-warning {
    background: #fff3cd;
    color: #664d03;
    border: 1px solid #ffecb5;
}

.alert-info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.alert::before {
    font-size: 16px;
    font-weight: 800;
}

.alert-success::before {
    content: "✓";
}

.alert-error::before {
    content: "✕";
}

.alert-warning::before {
    content: "⚠";
}

.alert-info::before {
    content: "ℹ";
}

/* ================================================================
   INDICADORES / BADGES
   ================================================================ */

.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.badge-primary {
    background: rgba(11, 123, 236, 0.15);
    color: var(--primary);
}

.badge-success {
    background: rgba(34, 168, 70, 0.15);
    color: var(--success);
}

.badge-danger {
    background: rgba(220, 53, 69, 0.15);
    color: var(--danger);
}

.badge-warning {
    background: rgba(255, 193, 7, 0.15);
    color: #664d03;
}

/* ================================================================
   RADIO BUTTONS (Como na imagem TR/TN/TP)
   ================================================================ */

.radio-group {
    display: flex;
    gap: 16px;
    align-items: center;
}

.radio-option {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.radio-option input[type="radio"] {
    cursor: pointer;
    width: 18px;
    height: 18px;
    accent-color: var(--primary);
}

.radio-option label {
    cursor: pointer;
    font-weight: 600;
    font-size: 13px;
    margin: 0;
}

/* ================================================================
   INPUTS NUMÉRICOS
   ================================================================ */

.input-number {
    text-align: right;
}

.currency-prefix {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-weight: 600;
    font-size: 12px;
    pointer-events: none;
}

/* ================================================================
   GRID RESPONSIVO
   ================================================================ */

@media (max-width: 1180px) {
    .form-row.row-3 {
        grid-template-columns: repeat(2, 1fr);
    }

    .form-row.row-4 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .form-row,
    .form-row.row-2,
    .form-row.row-3,
    .form-row.row-4 {
        grid-template-columns: 1fr;
    }

    .table {
        font-size: 12px;
    }

    .table th,
    .table td {
        padding: 8px;
    }

    .btn {
        min-width: 100px;
        min-height: 36px;
    }

    .calibradora-header {
        flex-direction: column;
        align-items: flex-start;
    }
}

/* ================================================================
   ESTADO DE CARREGAMENTO
   ================================================================ */

.loading {
    opacity: 0.6;
    pointer-events: none;
}

.spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(11, 123, 236, 0.3);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

</style>

<script>
/**
 * COMPONENTES JAVASCRIPT
 * Select Pesquisável
 */

class SelectSearch {
    constructor(element) {
        this.element = element;
        this.wrapper = element.parentElement;
        this.options = Array.from(element.querySelectorAll('option'));
        this.selectedValue = element.value;
        this.init();
    }

    init() {
        // Criar HTML do select customizado
        const searchDiv = document.createElement('div');
        searchDiv.className = 'select-wrapper';
        
        const selectSearch = document.createElement('div');
        selectSearch.className = 'select-search';
        
        const input = document.createElement('input');
        input.className = 'select-search-input';
        input.type = 'text';
        input.placeholder = this.element.querySelector('option[value=""]')?.textContent || 'Selecione...';
        
        const arrow = document.createElement('div');
        arrow.className = 'select-arrow';
        arrow.innerHTML = '▼';
        
        selectSearch.appendChild(input);
        selectSearch.appendChild(arrow);
        
        const dropdown = document.createElement('div');
        dropdown.className = 'select-dropdown';
        
        // Renderizar opções
        this.options.forEach((opt, idx) => {
            if (opt.value === '') return; // Skip placeholder
            
            const optDiv = document.createElement('div');
            optDiv.className = 'select-option';
            if (opt.value === this.selectedValue) optDiv.classList.add('selected');
            
            optDiv.innerHTML = `<span class="select-option-text">${opt.textContent}</span>`;
            
            optDiv.addEventListener('click', () => this.select(opt.value, opt.textContent));
            
            dropdown.appendChild(optDiv);
        });
        
        if (this.options.length === 1) {
            const emptyDiv = document.createElement('div');
            emptyDiv.className = 'select-empty';
            emptyDiv.textContent = 'Nenhuma opção disponível';
            dropdown.appendChild(emptyDiv);
        }
        
        searchDiv.appendChild(selectSearch);
        searchDiv.appendChild(dropdown);
        
        this.wrapper.insertBefore(searchDiv, this.element);
        this.element.style.display = 'none';
        
        this.selectSearch = selectSearch;
        this.dropdown = dropdown;
        this.input = input;
        this.optDivs = Array.from(dropdown.querySelectorAll('.select-option'));
        
        // Event listeners
        selectSearch.addEventListener('click', () => this.toggle());
        input.addEventListener('input', (e) => this.filter(e.target.value));
        input.addEventListener('keydown', (e) => this.handleKeyboard(e));
        
        document.addEventListener('click', (e) => {
            if (!this.wrapper.contains(e.target)) {
                this.close();
            }
        });
    }

    toggle() {
        if (this.dropdown.classList.contains('active')) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        this.selectSearch.classList.add('active');
        this.dropdown.classList.add('active');
        this.input.focus();
    }

    close() {
        this.selectSearch.classList.remove('active');
        this.dropdown.classList.remove('active');
        this.input.value = '';
        this.optDivs.forEach(opt => opt.style.display = '');
    }

    select(value, text) {
        this.selectedValue = value;
        this.element.value = value;
        
        this.optDivs.forEach(opt => opt.classList.remove('selected'));
        event.target.closest('.select-option').classList.add('selected');
        
        this.input.value = text;
        this.close();
        
        // Disparar evento de mudança
        this.element.dispatchEvent(new Event('change', { bubbles: true }));
    }

    filter(term) {
        const lowerTerm = term.toLowerCase();
        
        this.optDivs.forEach(opt => {
            const text = opt.textContent.toLowerCase();
            opt.style.display = text.includes(lowerTerm) ? '' : 'none';
        });
    }

    handleKeyboard(e) {
        if (e.key === 'Escape') {
            this.close();
        }
    }
}

// Inicializar todos os selects
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('select.select-search').forEach(el => {
        new SelectSearch(el);
    });
});
</script>
