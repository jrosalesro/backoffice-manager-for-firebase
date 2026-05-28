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
        if (!enabled) {
            if (prevPageBtn) prevPageBtn.disabled = true;
            if (nextPageBtn) nextPageBtn.disabled = true;
            if (createDocBtn) createDocBtn.disabled = true;
            if (deleteStructureBtn) deleteStructureBtn.disabled = true;
            if (viewStructureBtn) viewStructureBtn.disabled = true;
        }
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

    function valueType(value) {
        if (value === null || value === undefined) return 'null';
        if (Array.isArray(value)) return 'array';
        if (typeof value === 'object' && value._type === 'timestamp') return 'timestamp';
        if (typeof value === 'object') return 'map';
        return typeof value;
    }

    function compactValue(value) {
        if (value === null || value === undefined) return '—';
        if (typeof value === 'boolean') return value ? 'true' : 'false';
        if (typeof value === 'number') return String(value);
        if (typeof value === 'string') return value.length > 70 ? `${value.slice(0, 70)}…` : value;
        if (Array.isArray(value)) return `[${value.length}]`;
        if (typeof value === 'object' && value._type === 'timestamp') return value.iso || 'Timestamp';
        if (typeof value === 'object') return 'Object';
        return String(value);
    }

    function inputValueForField(value) {
        if (value === null || value === undefined) return '';
        if (typeof value === 'object') return JSON.stringify(value, null, 2);
        return String(value);
    }

    function parseFieldValue(raw, type) {
        if (type === 'number') {
            const n = Number(raw);
            if (Number.isNaN(n)) throw new Error('Invalid number.');
            return n;
        }
        if (type === 'boolean') return raw === true || raw === 'true' || raw === '1';
        if (type === 'array' || type === 'map' || type === 'timestamp' || type === 'null') {
            return String(raw).trim() === '' ? null : JSON.parse(raw);
        }
        return raw;
    }

    function clearTable(message = 'Enter a collection and click “Load”.') {
        if (!resultsBodyEl) return;
        if (resultsHeadRowEl) {
            resultsHeadRowEl.innerHTML = '<th>Doc ID</th><th>Fields</th><th class="bomff-col-actions">Actions</th>';
        }
        resultsBodyEl.innerHTML = `<tr><td colspan="3" class="bomff-center-muted">${escapeHtml(message)}</td></tr>`;
    }

    function detectColumns(docs) {
        const set = new Set();
        docs.forEach((doc) => Object.keys(doc.data || {}).forEach((key) => set.add(key)));
        return Array.from(set).sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' })).slice(0, 12);
    }

    function getDocFromCache(docId) {
        return lastDocs.find((doc) => doc.id === docId) || null;
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
                    <td class="bomff-doc-id-cell"><code>${escapeHtml(doc.id)}</code></td>
                    ${columns.map((col) => {
                        const value = data[col];
                        return `<td class="bomff-editable-cell" title="Double-click to edit" data-doc-id="${escapeHtml(doc.id)}" data-field="${escapeHtml(col)}" data-type="${escapeHtml(valueType(value))}">${escapeHtml(compactValue(value))}</td>`;
                    }).join('')}
                    <td class="bomff-muted-cell">${otherFields.length ? escapeHtml(otherFields.join(', ')) : '—'}</td>
                    <td class="bomff-col-actions">
                        <button class="button button-small" type="button" data-action="view" data-doc-id="${escapeHtml(doc.id)}">View</button>
                        <button class="button button-small" type="button" data-action="edit" data-doc-id="${escapeHtml(doc.id)}">Edit</button>
                        <button class="button button-small" type="button" data-action="delete" data-doc-id="${escapeHtml(doc.id)}">Delete</button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function ensureModals() {
        if (!document.getElementById('bomff-runtime-modal-style')) {
            const style = document.createElement('style');
            style.id = 'bomff-runtime-modal-style';
            style.textContent = `
                #bomff-results-table{border-collapse:separate;border-spacing:0;}
                #bomff-results-table th{position:sticky;top:32px;background:#fff;z-index:2;}
                #bomff-results-table td{vertical-align:middle;}
                #bomff-results-table tbody tr:hover{background:#f6f7f7;}
                .bomff-editable-cell{cursor:cell;position:relative;outline:1px solid transparent;max-width:240px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
                .bomff-editable-cell:hover{background:#eef6ff!important;outline:1px solid #72aee6;box-shadow:inset 0 0 0 1px #72aee6;}
                .bomff-editable-cell:hover:after{content:'✚';position:absolute;right:6px;top:50%;transform:translateY(-50%);font-size:11px;color:#2271b1;background:#eef6ff;}
                .bomff-doc-id-cell{cursor:default;}
                .bomff-muted-cell{color:#777;}
                .bomff-runtime-modal{display:none;position:fixed;z-index:100000;inset:0;background:rgba(0,0,0,.45);}
                .bomff-runtime-modal.is-open{display:block;}
                .bomff-runtime-panel{background:#fff;max-width:980px;margin:5vh auto;border-radius:8px;box-shadow:0 15px 45px rgba(0,0,0,.25);overflow:hidden;max-height:88vh;display:flex;flex-direction:column;}
                .bomff-runtime-panel--small{max-width:620px;}
                .bomff-runtime-header,.bomff-runtime-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;border-bottom:1px solid #ddd;}
                .bomff-runtime-footer{border-top:1px solid #ddd;border-bottom:0;justify-content:flex-end;}
                .bomff-runtime-header h2{margin:0;font-size:18px;}
                .bomff-runtime-body{padding:18px;overflow:auto;}
                .bomff-runtime-meta{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:14px;color:#555;}
                .bomff-edit-fields-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;margin-bottom:18px;}
                .bomff-edit-field-card{border:1px solid #dcdcde;border-radius:6px;padding:12px;background:#fff;}
                .bomff-edit-field-card label{display:flex;justify-content:space-between;gap:10px;font-weight:600;margin-bottom:8px;}
                .bomff-edit-field-type{font-weight:400;color:#777;font-size:12px;}
                .bomff-edit-field-card input[type=text],.bomff-edit-field-card input[type=number],.bomff-edit-field-card textarea{width:100%;box-sizing:border-box;}
                .bomff-edit-field-card textarea{min-height:90px;font-family:Consolas,Monaco,monospace;font-size:13px;}
                .bomff-advanced-toggle{margin:8px 0 10px;}
                .bomff-runtime-textarea{width:100%;min-height:300px;font-family:Consolas,Monaco,monospace;font-size:13px;line-height:1.45;}
                .bomff-runtime-textarea--small{min-height:180px;}
                .bomff-runtime-error{display:none;color:#b32d2e;margin-top:10px;}
                .bomff-hidden-force{display:none!important;}
            `;
            document.head.appendChild(style);
        }

        if (!document.getElementById('bomff-edit-document-modal')) {
            document.body.insertAdjacentHTML('beforeend', `
                <div id="bomff-edit-document-modal" class="bomff-runtime-modal" aria-hidden="true">
                    <div class="bomff-runtime-panel" role="dialog" aria-modal="true">
                        <div class="bomff-runtime-header">
                            <h2 id="bomff-edit-modal-title">Edit document</h2>
                            <button class="button" type="button" data-modal-close="bomff-edit-document-modal">✕</button>
                        </div>
                        <div class="bomff-runtime-body">
                            <div class="bomff-runtime-meta">
                                <div><strong>Collection:</strong> <code id="bomff-edit-modal-collection"></code></div>
                                <div><strong>Doc ID:</strong> <code id="bomff-edit-modal-docid"></code></div>
                            </div>
                            <div id="bomff-edit-fields-grid" class="bomff-edit-fields-grid"></div>
                            <p class="bomff-advanced-toggle">
                                <button id="bomff-toggle-advanced-json" class="button" type="button">Advanced JSON</button>
                            </p>
                            <div id="bomff-advanced-json-wrap" class="bomff-hidden-force">
                                <textarea id="bomff-edit-modal-json" class="bomff-runtime-textarea" spellcheck="false"></textarea>
                            </div>
                            <div id="bomff-edit-modal-error" class="bomff-runtime-error"></div>
                        </div>
                        <div class="bomff-runtime-footer">
                            <button class="button" type="button" data-modal-close="bomff-edit-document-modal">Cancel</button>
                            <button id="bomff-edit-modal-save" class="button button-primary" type="button">Save changes</button>
                        </div>
                    </div>
                </div>
            `);
        }

        if (!document.getElementById('bomff-field-edit-modal')) {
            document.body.insertAdjacentHTML('beforeend', `
                <div id="bomff-field-edit-modal" class="bomff-runtime-modal" aria-hidden="true">
                    <div class="bomff-runtime-panel bomff-runtime-panel--small" role="dialog" aria-modal="true">
                        <div class="bomff-runtime-header">
                            <h2>Quick field edit</h2>
                            <button class="button" type="button" data-modal-close="bomff-field-edit-modal">✕</button>
                        </div>
                        <div class="bomff-runtime-body">
                            <div class="bomff-runtime-meta">
                                <div><strong>Doc ID:</strong> <code id="bomff-field-modal-docid"></code></div>
                                <div><strong>Field:</strong> <code id="bomff-field-modal-field"></code></div>
                                <div><strong>Type:</strong> <code id="bomff-field-modal-type"></code></div>
                            </div>
                            <div id="bomff-field-modal-input-wrap"></div>
                            <div id="bomff-field-modal-error" class="bomff-runtime-error"></div>
                        </div>
                        <div class="bomff-runtime-footer">
                            <button class="button" type="button" data-modal-close="bomff-field-edit-modal">Cancel</button>
                            <button id="bomff-field-modal-save" class="button button-primary" type="button">Save field</button>
                        </div>
                    </div>
                </div>
            `);
        }

        document.querySelectorAll('[data-modal-close]').forEach((btn) => {
            if (btn.dataset.boundClose) return;
            btn.dataset.boundClose = '1';
            btn.addEventListener('click', () => closeModal(btn.getAttribute('data-modal-close')));
        });

        const editSave = document.getElementById('bomff-edit-modal-save');
        if (editSave && !editSave.dataset.boundSave) {
            editSave.dataset.boundSave = '1';
            editSave.addEventListener('click', saveEditModal);
        }

        const fieldSave = document.getElementById('bomff-field-modal-save');
        if (fieldSave && !fieldSave.dataset.boundSave) {
            fieldSave.dataset.boundSave = '1';
            fieldSave.addEventListener('click', saveFieldModal);
        }

        const advancedToggle = document.getElementById('bomff-toggle-advanced-json');
        if (advancedToggle && !advancedToggle.dataset.boundToggle) {
            advancedToggle.dataset.boundToggle = '1';
            advancedToggle.addEventListener('click', () => {
                syncVisualFieldsToJson();
                const wrap = document.getElementById('bomff-advanced-json-wrap');
                wrap.classList.toggle('bomff-hidden-force');
            });
        }
    }

    function openModal(id) {
        ensureModals();
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    function setModalError(id, message) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = message || '';
        el.style.display = message ? 'block' : 'none';
    }

    function createFieldInput(name, value, readOnly) {
        const type = valueType(value);
        const id = `bomff-edit-field-${Math.random().toString(36).slice(2)}`;
        const readonlyAttr = readOnly ? 'disabled' : '';

        let input = '';
        if (type === 'boolean') {
            input = `<label style="justify-content:flex-start;font-weight:400;"><input id="${id}" class="bomff-edit-field-input" data-field="${escapeHtml(name)}" data-type="boolean" type="checkbox" ${value ? 'checked' : ''} ${readonlyAttr}> true / enabled</label>`;
        } else if (type === 'number') {
            input = `<input id="${id}" class="bomff-edit-field-input" data-field="${escapeHtml(name)}" data-type="number" type="number" step="any" value="${escapeHtml(inputValueForField(value))}" ${readonlyAttr}>`;
        } else if (type === 'array' || type === 'map' || type === 'timestamp' || type === 'null') {
            input = `<textarea id="${id}" class="bomff-edit-field-input" data-field="${escapeHtml(name)}" data-type="${escapeHtml(type)}" ${readonlyAttr}>${escapeHtml(inputValueForField(value))}</textarea>`;
        } else {
            input = `<textarea id="${id}" class="bomff-edit-field-input" data-field="${escapeHtml(name)}" data-type="string" ${readonlyAttr}>${escapeHtml(inputValueForField(value))}</textarea>`;
        }

        return `
            <div class="bomff-edit-field-card">
                <label for="${id}"><span>${escapeHtml(name)}</span><span class="bomff-edit-field-type">${escapeHtml(type)}</span></label>
                ${input}
            </div>
        `;
    }

    function renderVisualEditFields(data, readOnly = false) {
        const grid = document.getElementById('bomff-edit-fields-grid');
        if (!grid) return;
        const keys = Object.keys(data || {}).sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }));
        grid.innerHTML = keys.length
            ? keys.map((key) => createFieldInput(key, data[key], readOnly)).join('')
            : '<p class="description">This document has no fields.</p>';

        grid.querySelectorAll('.bomff-edit-field-input').forEach((input) => {
            input.addEventListener('input', () => syncVisualFieldsToJson());
            input.addEventListener('change', () => syncVisualFieldsToJson());
        });
    }

    function collectVisualFields() {
        const out = {};
        document.querySelectorAll('#bomff-edit-fields-grid .bomff-edit-field-input').forEach((input) => {
            const field = input.dataset.field;
            const type = input.dataset.type;
            const raw = input.type === 'checkbox' ? input.checked : input.value;
            out[field] = parseFieldValue(raw, type);
        });
        return out;
    }

    function syncVisualFieldsToJson() {
        if (!editState || editState.readOnly) return;
        try {
            const data = collectVisualFields();
            const textarea = document.getElementById('bomff-edit-modal-json');
            textarea.value = JSON.stringify(data, null, 2);
            setModalError('bomff-edit-modal-error', '');
        } catch (e) {
            setModalError('bomff-edit-modal-error', `Invalid visual field value: ${e.message}`);
        }
    }

    function syncJsonToVisualFields() {
        if (!editState || editState.readOnly) return;
        const textarea = document.getElementById('bomff-edit-modal-json');
        const data = JSON.parse(textarea.value || '{}');
        renderVisualEditFields(data, false);
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

            try { window.localStorage.setItem('bomff_last_collection', collection); } catch (e) {}

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
        const data = await wpAjax('bomff_get_document', { collection: currentCollection, docId });
        return data.document;
    }

    async function viewDocument(docId) {
        try {
            const doc = await getFreshDocument(docId);
            editState = { docId, data: doc.data || {}, readOnly: true };
            document.getElementById('bomff-edit-modal-title').textContent = 'View document';
            document.getElementById('bomff-edit-modal-collection').textContent = currentCollection;
            document.getElementById('bomff-edit-modal-docid').textContent = docId;
            document.getElementById('bomff-edit-modal-json').value = JSON.stringify(editState.data, null, 2);
            document.getElementById('bomff-edit-modal-json').readOnly = true;
            document.getElementById('bomff-edit-modal-save').style.display = 'none';
            document.getElementById('bomff-advanced-json-wrap').classList.add('bomff-hidden-force');
            renderVisualEditFields(editState.data, true);
            setModalError('bomff-edit-modal-error', '');
            openModal('bomff-edit-document-modal');
        } catch (e) {
            console.error(e);
            setMessage(e.message || 'Could not load document.', false);
        }
    }

    async function editDocument(docId) {
        try {
            const doc = await getFreshDocument(docId);
            editState = { docId, data: doc.data || {}, readOnly: false };
            document.getElementById('bomff-edit-modal-title').textContent = 'Edit document';
            document.getElementById('bomff-edit-modal-collection').textContent = currentCollection;
            document.getElementById('bomff-edit-modal-docid').textContent = docId;
            document.getElementById('bomff-edit-modal-json').value = JSON.stringify(editState.data, null, 2);
            document.getElementById('bomff-edit-modal-json').readOnly = false;
            document.getElementById('bomff-edit-modal-save').style.display = '';
            document.getElementById('bomff-advanced-json-wrap').classList.add('bomff-hidden-force');
            renderVisualEditFields(editState.data, false);
            setModalError('bomff-edit-modal-error', '');
            openModal('bomff-edit-document-modal');
        } catch (e) {
            console.error(e);
            setMessage(e.message || 'Could not load document.', false);
        }
    }

    async function saveEditModal() {
        if (!editState || editState.readOnly) return;
        const saveBtn = document.getElementById('bomff-edit-modal-save');

        let parsed;
        try {
            if (document.getElementById('bomff-advanced-json-wrap').classList.contains('bomff-hidden-force')) {
                syncVisualFieldsToJson();
            } else {
                syncJsonToVisualFields();
            }
            parsed = JSON.parse(document.getElementById('bomff-edit-modal-json').value || '{}');
        } catch (e) {
            setModalError('bomff-edit-modal-error', `Invalid data: ${e.message}`);
            return;
        }

        try {
            saveBtn.disabled = true;
            await wpAjax('bomff_save_document', {
                collection: currentCollection,
                docId: editState.docId,
                data: JSON.stringify(parsed),
            });
            closeModal('bomff-edit-document-modal');
            setMessage('Document saved.', true);
            await loadCollection(currentPageToken);
        } catch (e) {
            console.error(e);
            setModalError('bomff-edit-modal-error', e.message || 'Could not save document.');
        } finally {
            saveBtn.disabled = false;
        }
    }

    async function openFieldEditor(docId, field) {
        try {
            const cached = getDocFromCache(docId);
            const doc = cached || await getFreshDocument(docId);
            const value = (doc.data || {})[field];
            const type = valueType(value);
            fieldEditState = { docId, field, type, docData: doc.data || {} };

            document.getElementById('bomff-field-modal-docid').textContent = docId;
            document.getElementById('bomff-field-modal-field').textContent = field;
            document.getElementById('bomff-field-modal-type').textContent = type;

            const wrap = document.getElementById('bomff-field-modal-input-wrap');
            if (type === 'boolean') {
                wrap.innerHTML = `<label style="display:flex;gap:8px;align-items:center;"><input id="bomff-field-modal-value" type="checkbox" ${value ? 'checked' : ''} /> <span>Enabled / true</span></label>`;
            } else if (type === 'number') {
                wrap.innerHTML = `<input id="bomff-field-modal-value" type="number" step="any" class="regular-text" value="${escapeHtml(inputValueForField(value))}" />`;
            } else if (type === 'array' || type === 'map' || type === 'timestamp' || type === 'null') {
                wrap.innerHTML = `<textarea id="bomff-field-modal-value" class="bomff-runtime-textarea bomff-runtime-textarea--small" spellcheck="false">${escapeHtml(inputValueForField(value))}</textarea>`;
            } else {
                wrap.innerHTML = `<textarea id="bomff-field-modal-value" class="bomff-runtime-textarea bomff-runtime-textarea--small" spellcheck="false">${escapeHtml(inputValueForField(value))}</textarea>`;
            }

            setModalError('bomff-field-modal-error', '');
            openModal('bomff-field-edit-modal');
        } catch (e) {
            console.error(e);
            setMessage(e.message || 'Could not load field.', false);
        }
    }

    async function saveFieldModal() {
        if (!fieldEditState) return;
        const input = document.getElementById('bomff-field-modal-value');
        const saveBtn = document.getElementById('bomff-field-modal-save');

        let nextValue;
        try {
            const raw = input.type === 'checkbox' ? input.checked : input.value;
            nextValue = parseFieldValue(raw, fieldEditState.type);
        } catch (e) {
            setModalError('bomff-field-modal-error', e.message || 'Invalid field value.');
            return;
        }

        const nextData = Object.assign({}, fieldEditState.docData, { [fieldEditState.field]: nextValue });

        try {
            saveBtn.disabled = true;
            await wpAjax('bomff_save_document', {
                collection: currentCollection,
                docId: fieldEditState.docId,
                data: JSON.stringify(nextData),
            });
            closeModal('bomff-field-edit-modal');
            setMessage('Field saved.', true);
            await loadCollection(currentPageToken);
        } catch (e) {
            console.error(e);
            setModalError('bomff-field-modal-error', e.message || 'Could not save field.');
        } finally {
            saveBtn.disabled = false;
        }
    }

    async function deleteDocument(docId) {
        if (!confirm(`Delete document “${docId}”?`)) return;
        try {
            await wpAjax('bomff_delete_document', { collection: currentCollection, docId });
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
                if (!fields.has(key)) fields.set(key, { name: key, type: valueType(value), required: false, auto: false });
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
            const data = await wpAjax('bomff_save_structure', { collection: currentCollection, fields: JSON.stringify(fields) });
            activeStructure = data.structure;
            setStructureMessage(`Structure saved for “${currentCollection}”.`, true);
            if (viewStructureBtn) viewStructureBtn.disabled = false;
            if (deleteStructureBtn) deleteStructureBtn.disabled = false;
            if (createDocBtn) createDocBtn.disabled = false;
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
                if (createDocBtn) createDocBtn.disabled = false;
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
            if (createDocBtn) createDocBtn.disabled = true;
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

    function createDocument() {
        const collection = activeStructure && activeStructure.collection ? activeStructure.collection : (collectionInputEl?.value || '').trim();
        const docId = prompt('New document ID:');
        if (!docId) return;
        const json = prompt('Document JSON:', '{}');
        if (json === null) return;
        try { JSON.parse(json); } catch (e) {
            setStructureMessage(`Invalid JSON: ${e.message}`, false);
            return;
        }
        currentCollection = collection;
        if (collectionInputEl) collectionInputEl.value = collection;
        wpAjax('bomff_save_document', { collection, docId, data: json })
            .then(() => { setStructureMessage('Document created.', true); return loadCollection(''); })
            .catch((e) => { console.error(e); setStructureMessage(e.message || 'Could not create document.', false); });
    }

    function bindEvents() {
        ensureModals();

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

        if (nextPageBtn) nextPageBtn.addEventListener('click', () => {
            if (!nextPageToken) return;
            pageStack.push(currentPageToken || '');
            loadCollection(nextPageToken);
        });

        if (prevPageBtn) prevPageBtn.addEventListener('click', () => {
            const prevToken = pageStack.pop();
            loadCollection(prevToken || '');
        });

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

            resultsBodyEl.addEventListener('dblclick', (event) => {
                const cell = event.target.closest('.bomff-editable-cell');
                if (!cell) return;
                openFieldEditor(cell.dataset.docId, cell.dataset.field);
            });
        }

        if (importStructureBtn) importStructureBtn.addEventListener('click', importStructure);
        if (viewStructureBtn) viewStructureBtn.addEventListener('click', viewStructure);
        if (deleteStructureBtn) deleteStructureBtn.addEventListener('click', deleteStructure);
        if (createDocBtn) createDocBtn.addEventListener('click', createDocument);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeModal('bomff-edit-document-modal');
                closeModal('bomff-field-edit-modal');
            }
        });
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

            try {
                const saved = window.localStorage.getItem('bomff_last_collection');
                if (saved && collectionInputEl && collectionInputEl.value === saved && !autoLoadedLastCollection) {
                    autoLoadedLastCollection = true;
                    loadCollection('');
                }
            } catch (e) {}
        } catch (e) {
            console.error(e);
            setConnectionStatus(e.message || 'Could not connect to Firebase.', false);
            showConfigWarning(true);
            clearTable('Could not connect to Firebase.');
        }
    }

    init();
});
