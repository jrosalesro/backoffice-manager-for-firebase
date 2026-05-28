document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.bomffFirebaseConfig || {};

    const statusConnectionEl = document.getElementById('firebase-connection-status');
    const configWarningEl = document.getElementById('bomff-config-warning');
    const collectionInputEl = document.getElementById('bomff-collection-name');
    const loadCollectionBtn = document.getElementById('bomff-load-collection');
    const clearResultsBtn = document.getElementById('bomff-clear-results');
    const loadDocBtn = document.getElementById('bomff-load-doc');
    const docIdInputEl = document.getElementById('bomff-doc-id');
    const pageSizeEl = document.getElementById('bomff-page-size');
    const prevPageBtn = document.getElementById('bomff-prev-page');
    const nextPageBtn = document.getElementById('bomff-next-page');
    const resultsBodyEl = document.getElementById('bomff-collection-results');
    const resultsHeadRowEl = document.getElementById('bomff-results-head-row');
    const collectionMsgEl = document.getElementById('bomff-collection-msg');

    const importStructureBtn = document.getElementById('bomff-import-structure');
    const viewStructureBtn = document.getElementById('bomff-view-structure');
    const createDocBtn = document.getElementById('bomff-create-doc');
    const deleteStructureBtn = document.getElementById('bomff-delete-structure');
    const structureMsgEl = document.getElementById('bomff-structure-msg');

    let currentCollection = '';
    let nextPageToken = '';
    let pageStack = [];
    let currentPageToken = '';
    let lastDocs = [];
    let activeStructure = null;
    let editState = null;
    let fieldEditState = null;
    let autoLoadedLastCollection = false;

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function setConnectionStatus(text, ok = true) {
        if (!statusConnectionEl) return;
        statusConnectionEl.textContent = text;
        statusConnectionEl.className = ok ? 'bomff-status-success bomff-hidden' : 'bomff-status-error bomff-hidden';
    }

    function setMessage(text, ok = true) {
        if (!collectionMsgEl) return;
        collectionMsgEl.textContent = text || '';
        collectionMsgEl.style.color = ok ? 'green' : 'red';
    }

    function setStructureMessage(text, ok = true) {
        if (!structureMsgEl) return;
        structureMsgEl.textContent = text || '';
        structureMsgEl.style.color = ok ? 'green' : 'red';
    }

    function showConfigWarning(show) {
        if (!configWarningEl) return;
        configWarningEl.classList.toggle('bomff-hidden', !show);
    }

    function setExplorerEnabled(enabled) {
        [collectionInputEl, loadCollectionBtn, clearResultsBtn, loadDocBtn, docIdInputEl, pageSizeEl, importStructureBtn].forEach((el) => {
            if (el) el.disabled = !enabled;
        });
    }

    function ensureModals() {
        if (document.getElementById('bomff-runtime-modal-style')) return;

        const style = document.createElement('style');
        style.id = 'bomff-runtime-modal-style';
        style.textContent = `
            #bomff-results-table{
                border-collapse:separate;
                border-spacing:0;
            }

            /* FIX: removed sticky header causing first row overlap */
            #bomff-results-table th{
                position:static;
                background:#fff;
                z-index:auto;
            }

            #bomff-results-table td{
                vertical-align:middle;
            }

            #bomff-results-table tbody tr:hover{
                background:#f6f7f7;
            }

            .bomff-editable-cell{
                cursor:cell;
                position:relative;
                outline:1px solid transparent;
                max-width:240px;
                white-space:nowrap;
                overflow:hidden;
                text-overflow:ellipsis;
                transition:all .12s ease;
            }

            .bomff-editable-cell:hover{
                background:#eef6ff!important;
                outline:1px solid #72aee6;
                box-shadow:inset 0 0 0 1px #72aee6;
            }

            .bomff-editable-cell:hover:after{
                content:'✚';
                position:absolute;
                right:6px;
                top:50%;
                transform:translateY(-50%);
                font-size:11px;
                color:#2271b1;
                background:#eef6ff;
            }
        `;

        document.head.appendChild(style);
    }

    ensureModals();
});