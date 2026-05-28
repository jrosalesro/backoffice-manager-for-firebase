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
        statusConnectionEl.className = ok ? 'bomff-status-success' : 'bomff-status-error';
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
        [collectionInputEl, loadCollectionBtn, clearResultsBtn, loadDocBtn, docIdInputEl, pageSizeEl, prevPageBtn, nextPageBtn, importStructureBtn, createDocBtn, deleteStructureBtn, viewStructureBtn].forEach((el) => {
            if (el) el.disabled = !enabled;
        });
    }

    async function wpAjax(action, payload = {}) {
        if (!cfg.ajaxUrl) throw new Error('AJAX URL not available.');

        const fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', cfg.nonce || '');

        Object.entries(payload).forEach(([key, value]) => {
            fd.append(key, value === undefined || value === null ? '' : value);
        });

        const res = await fetch(cfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: fd,
        });

        const json = await res.json().catch(() => null);
        if (!json) throw new Error('Invalid server response.');
        if (!json.success) {
            throw new Error(json.data && json.data.message ? json.data.message : 'Request failed.');
        }
        return json.data;
    }

    function compactValue(value) {
        if (value === null || value === undefined) return '—';
        if (typeof value === 'boolean') return value ? 'true' : 'false';
        if (typeof value === 'number') return String(value);
        if (typeof value === 'string') {
            if (value.length > 60) return `${value.slice(0, 60)}…`;
            return value;
        }
        if (Array.isArray(value)) return `[${value.length}]`;
        if (typeof value === 'object' && value._type === 'timestamp') return value.iso || 'Timestamp';
        if (typeof value === 'object') return 'Object';
        return String(value);
    }

    function clearTable(message = 'Enter a collection and click “Load”.') {
        if (!resultsBodyEl) return;
        if (resultsHeadRowEl) {
            resultsHeadRowEl.innerHTML = `
                <th>Doc ID</th>
                <th>Fields</th>
                <th class="bomff-col-actions">Actions</th>
            `;
        }
        resultsBodyEl.innerHTML = `
            <tr>
                <td colspan="3" class="bomff-center-muted">${escapeHtml(message)}</td>
            </tr>
        `;
    }

    function detectColumns(docs) {
        const set = new Set();
        docs.forEach((doc) => {
            Object.keys(doc.data || {}).forEach((key) => set.add(key));
        });
        return Array.from(set).sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' })).slice(0, 12);
    }

    function renderDocuments(docs) {
        lastDocs = docs || [];

        if (!resultsBodyEl || !resultsHeadRowEl) return;

        if (!lastDocs.length) {
            clearTable('No documents found.');
            return;
        }

        const columns = detectColumns(lastDocs);
        const usedColumns = new Set(columns);

        resultsHeadRowEl.innerHTML = `
            <th>Doc ID</th>
            ${columns.map((col) => `<th>${escapeHtml(col)}</th>`).join('')}
            <th>Other fields</th>
            <th class="bomff-col-actions">Actions</th>
        `;

        resultsBodyEl.innerHTML = lastDocs.map((doc) => {
            const data = doc.data || {};
            const otherFields = Object.keys(data).filter((key) => !usedColumns.has(key));

            return `
                <tr data-doc-id="${escapeHtml(doc.id)}">
                    <td><code>${escapeHtml(doc.id)}</code></td>
                    ${columns.map((col) => `<td>${escapeHtml(compactValue(data[col]))}</td>`).join('')}
                    <td>${otherFields.length ? escapeHtml(otherFields.join(', ')) : '—'}</td>
                    <td class="bomff-col-actions">
                        <button class="button button-small" type="button" data-action="view" data-doc-id="${escapeHtml(doc.id)}">View</button>
                        <button class="button button-small" type="button" data-action="edit" data-doc-id="${escapeHtml(doc.id)}">Edit</button>
                        <button class="button button-small" type="button" data-action="delete" data-doc-id="${escapeHtml(doc.id)}">Delete</button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    async function loadCollection(pageToken = '') {
        const collection = (collectionInputEl?.value || '').trim();
        if (!collection) {
            setMessage('Enter a collection name.', false);
            return;
        }

        currentCollection = collection;
        currentPageToken = pageToken || '';

        try {
            setMessage(`Loading “${collection}”…`, true);
            if (loadCollectionBtn) loadCollectionBtn.disabled = true;

            const data = await wpAjax('bomff_list_documents', {
                collection,
                pageSize: pageSizeEl?.value || 25,
                pageToken: currentPageToken,
            });

            nextPageToken = data.nextPageToken || '';
            renderDocuments(data.documents || []);

            if (prevPageBtn) prevPageBtn.disabled = pageStack.length === 0;
            if (nextPageBtn) nextPageBtn.disabled = !nextPageToken;

            try {
                window.localStorage.setItem('bomff_last_collection', collection);
            } catch (e) {}

            setMessage(`Loaded ${(data.documents || []).length} document(s).`, true);
        } catch (e) {
            console.error(e);
            clearTable('Could not load documents.');
            setMessage(e.message || 'Could not load documents.', false);
        } finally {
            if (loadCollectionBtn) loadCollectionBtn.disabled = false;
        }
    }

    async function loadSingleDocument() {
        const collection = (collectionInputEl?.value || '').trim();
        const docId = (docIdInputEl?.value || '').trim();

        if (!collection || !docId) {
            setMessage('Enter collection and document ID.', false);
            return;
        }

        try {
            setMessage(`Loading document “${docId}”…`, true);
            const data = await wpAjax('bomff_get_document', { collection, docId });
            currentCollection = collection;
            nextPageToken = '';
            pageStack = [];
            renderDocuments(data.document ? [data.document] : []);
            setMessage('Loaded 1 document.', true);
        } catch (e) {
            console.error(e);
            setMessage(e.message || 'Could not load document.', false);
        }
    }

    async function getFreshDocument(docId) {
        const data = await wpAjax('bomff_get_document', {
            collection: currentCollection,
            docId,
        });
        return data.document;
    }

    async function viewDocument(docId) {
        try {
            const doc = await getFreshDocument(docId);
            const pretty = JSON.stringify(doc.data || {}, null, 2);
            const popup = window.open('', '_blank', 'width=900,height=700,scrollbars=yes,resizable=yes');
            if (popup) {
                popup.document.write(`<pre style="white-space:pre-wrap;font:14px/1.4 monospace;padding:20px;">${escapeHtml(pretty)}</pre>`);
                popup.document.close();
            } else {
                alert(pretty);
            }
        } catch (e) {
            console.error(e);
            setMessage(e.message || 'Could not load document.', false);
        }
    }

    async function editDocument(docId) {
        try {
            const doc = await getFreshDocument(docId);
            const current = JSON.stringify(doc.data || {}, null, 2);
            const updated = prompt('Edit document JSON:', current);

            if (updated === null) return;

            let parsed;
            try {
                parsed = JSON.parse(updated);
            } catch (e) {
                setMessage(`Invalid JSON: ${e.message}`, false);
                return;
            }

            await wpAjax('bomff_save_document', {
                collection: currentCollection,
                docId,
                data: JSON.stringify(parsed),
            });

            setMessage('Document saved.', true);
            await loadCollection(currentPageToken);
        } catch (e) {
            console.error(e);
            setMessage(e.message || 'Could not save document.', false);
        }
    }

    async function deleteDocument(docId) {
        if (!confirm(`Delete document “${docId}”?`)) return;

        try {
            await wpAjax('bomff_delete_document', {
                collection: currentCollection,
                docId,
            });

            setMessage('Document deleted.', true);
            await loadCollection(currentPageToken);
        } catch (e) {
            console.error(e);
            setMessage(e.message || 'Could not delete document.', false);
        }
    }

    function inferStructureFromDocs(docs) {
        const fields = new Map();
        docs.forEach((doc) => {
            Object.entries(doc.data || {}).forEach(([key, value]) => {
                if (!fields.has(key)) {
                    fields.set(key, {
                        name: key,
                        type: Array.isArray(value) ? 'array' : (value && typeof value === 'object' ? 'map' : typeof value),
                        required: false,
                        auto: false,
                    });
                }
            });
        });
        return Array.from(fields.values()).sort((a, b) => a.name.localeCompare(b.name));
    }

    async function importStructure() {
        if (!currentCollection || !lastDocs.length) {
            setStructureMessage('Load a collection first.', false);
            return;
        }

        const fields = inferStructureFromDocs(lastDocs);
        if (!fields.length) {
            setStructureMessage('No fields detected.', false);
            return;
        }

        try {
            const data = await wpAjax('bomff_save_structure', {
                collection: currentCollection,
                fields: JSON.stringify(fields),
            });
            activeStructure = data.structure;
            setStructureMessage(`Structure saved for “${currentCollection}”.`, true);
            if (viewStructureBtn) viewStructureBtn.disabled = false;
            if (deleteStructureBtn) deleteStructureBtn.disabled = false;
        } catch (e) {
            console.error(e);
            setStructureMessage(e.message || 'Could not save structure.', false);
        }
    }

    async function loadStructure() {
        try {
            const data = await wpAjax('bomff_get_structure', {});
            activeStructure = data.structure && Object.keys(data.structure).length ? data.structure : null;
            if (activeStructure) {
                setStructureMessage(`Active structure: ${activeStructure.collection}`, true);
                if (viewStructureBtn) viewStructureBtn.disabled = false;
                if (deleteStructureBtn) deleteStructureBtn.disabled = false;
            } else {
                setStructureMessage('No active structure.', false);
            }
        } catch (e) {
            console.error(e);
            setStructureMessage(e.message || 'Could not load structure.', false);
        }
    }

    async function deleteStructure() {
        if (!confirm('Delete active structure?')) return;
        try {
            await wpAjax('bomff_delete_structure', {});
            activeStructure = null;
            setStructureMessage('Structure deleted.', true);
            if (viewStructureBtn) viewStructureBtn.disabled = true;
            if (deleteStructureBtn) deleteStructureBtn.disabled = true;
        } catch (e) {
            console.error(e);
            setStructureMessage(e.message || 'Could not delete structure.', false);
        }
    }

    function viewStructure() {
        if (!activeStructure) {
            setStructureMessage('No active structure.', false);
            return;
        }
        alert(JSON.stringify(activeStructure, null, 2));
    }

    function bindEvents() {
        if (loadCollectionBtn) {
            loadCollectionBtn.addEventListener('click', () => {
                pageStack = [];
                nextPageToken = '';
                loadCollection('');
            });
        }

        if (clearResultsBtn) {
            clearResultsBtn.addEventListener('click', () => {
                clearTable();
                setMessage('');
                nextPageToken = '';
                pageStack = [];
                if (prevPageBtn) prevPageBtn.disabled = true;
                if (nextPageBtn) nextPageBtn.disabled = true;
            });
        }

        if (loadDocBtn) loadDocBtn.addEventListener('click', loadSingleDocument);

        if (collectionInputEl) {
            collectionInputEl.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    pageStack = [];
                    nextPageToken = '';
                    loadCollection('');
                }
            });

            try {
                const saved = window.localStorage.getItem('bomff_last_collection');
                if (saved && !collectionInputEl.value) collectionInputEl.value = saved;
            } catch (e) {}
        }

        if (nextPageBtn) {
            nextPageBtn.addEventListener('click', () => {
                if (!nextPageToken) return;
                pageStack.push(currentPageToken || '');
                loadCollection(nextPageToken);
            });
        }

        if (prevPageBtn) {
            prevPageBtn.addEventListener('click', () => {
                const prevToken = pageStack.pop();
                loadCollection(prevToken || '');
            });
        }

        if (resultsBodyEl) {
            resultsBodyEl.addEventListener('click', (event) => {
                const button = event.target.closest('button[data-action]');
                if (!button) return;

                const action = button.dataset.action;
                const docId = button.dataset.docId;

                if (!docId) return;

                if (action === 'view') viewDocument(docId);
                if (action === 'edit') editDocument(docId);
                if (action === 'delete') deleteDocument(docId);
            });
        }

        if (importStructureBtn) importStructureBtn.addEventListener('click', importStructure);
        if (viewStructureBtn) viewStructureBtn.addEventListener('click', viewStructure);
        if (deleteStructureBtn) deleteStructureBtn.addEventListener('click', deleteStructure);

        if (createDocBtn) {
            createDocBtn.addEventListener('click', () => {
                const collection = activeStructure && activeStructure.collection ? activeStructure.collection : (collectionInputEl?.value || '').trim();
                const docId = prompt('New document ID:');
                if (!docId) return;
                const json = prompt('Document JSON:', '{}');
                if (json === null) return;

                try {
                    JSON.parse(json);
                } catch (e) {
                    setStructureMessage(`Invalid JSON: ${e.message}`, false);
                    return;
                }

                currentCollection = collection;
                if (collectionInputEl) collectionInputEl.value = collection;

                wpAjax('bomff_save_document', {
                    collection,
                    docId,
                    data: json,
                })
                    .then(() => {
                        setStructureMessage('Document created.', true);
                        return loadCollection('');
                    })
                    .catch((e) => {
                        console.error(e);
                        setStructureMessage(e.message || 'Could not create document.', false);
                    });
            });
        }
    }

    async function init() {
        if (!statusConnectionEl && !collectionInputEl) return;

        bindEvents();
        setExplorerEnabled(false);

        try {
            const status = await wpAjax('bomff_get_status', {});
            if (!status.configured) {
                setConnectionStatus('Not configured', false);
                showConfigWarning(true);
                clearTable('Configure Firebase first.');
                return;
            }

            setConnectionStatus(`Connected to ${status.projectId || 'Firebase'}`, true);
            showConfigWarning(false);
            setExplorerEnabled(true);
            if (nextPageBtn) nextPageBtn.disabled = true;
            if (prevPageBtn) prevPageBtn.disabled = true;
            await loadStructure();
        } catch (e) {
            console.error(e);
            setConnectionStatus(e.message || 'Could not connect to Firebase.', false);
            showConfigWarning(true);
            clearTable('Could not connect to Firebase.');
        }
    }

    init();
});
