document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.bomffFirebaseConfig || {};
    const $ = (id) => document.getElementById(id);

    const els = {
        warning: $('bomff-config-warning'),
        collection: $('bomff-collection-name'),
        load: $('bomff-load-collection'),
        clear: $('bomff-clear-results'),
        loadDoc: $('bomff-load-doc'),
        docId: $('bomff-doc-id'),
        pageSize: $('bomff-page-size'),
        prev: $('bomff-prev-page'),
        next: $('bomff-next-page'),
        body: $('bomff-collection-results'),
        head: $('bomff-results-head-row'),
        msg: $('bomff-collection-msg'),
        importStructure: $('bomff-import-structure'),
        viewStructure: $('bomff-view-structure'),
        createDoc: $('bomff-create-doc'),
        deleteStructure: $('bomff-delete-structure'),
        structureMsg: $('bomff-structure-msg'),
    };

    let currentCollection = '';
    let currentPageToken = '';
    let nextPageToken = '';
    let pageStack = [];
    let lastDocs = [];
    let activeStructure = null;
    let editState = null;
    let fieldEditState = null;
    let didAutoload = false;

    const esc = (v) => String(v ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');

    const showMsg = (text, ok = true) => {
        if (!els.msg) return;
        els.msg.textContent = text || '';
        els.msg.style.color = ok ? 'green' : 'red';
    };

    const showStructureMsg = (text, ok = true) => {
        if (!els.structureMsg) return;
        els.structureMsg.textContent = text || '';
        els.structureMsg.style.color = ok ? 'green' : 'red';
    };

    const typeOf = (value, fieldName = '') => {
        if (value === null || value === undefined) return 'null';
        if (Array.isArray(value)) return 'array';
        if (typeof value === 'object' && value._type === 'timestamp') return 'timestamp';
        if (typeof value === 'object') return 'map';
        if (typeof value === 'string') {
            const looksLikeDateName = /(date|fecha|created|updated|time|at)$/i.test(fieldName);
            if (/^\d{4}-\d{2}-\d{2}(T.*)?$/.test(value) || looksLikeDateName) return 'date-string';
        }
        return typeof value;
    };

    const defaultValueForType = (type) => {
        if (type === 'number') return 0;
        if (type === 'boolean') return false;
        if (type === 'array') return [];
        if (type === 'map') return {};
        if (type === 'timestamp') return { _type: 'timestamp', iso: new Date().toISOString() };
        if (type === 'date-string') return new Date().toISOString().slice(0, 10);
        return '';
    };

    const compact = (value) => {
        if (value === null || value === undefined) return '—';
        if (typeof value === 'boolean') return value ? 'true' : 'false';
        if (typeof value === 'number') return String(value);
        if (typeof value === 'string') return value.length > 70 ? `${value.slice(0, 70)}…` : value;
        if (Array.isArray(value)) return `[${value.length}]`;
        if (typeof value === 'object' && value._type === 'timestamp') return value.iso || 'Timestamp';
        return 'Object';
    };

    async function ajax(action, payload = {}) {
        const fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', cfg.nonce || '');
        Object.entries(payload).forEach(([key, value]) => fd.append(key, value ?? ''));
        const res = await fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd });
        const json = await res.json().catch(() => null);
        if (!json) throw new Error('Invalid server response.');
        if (!json.success) throw new Error(json.data?.message || 'Request failed.');
        return json.data;
    }

    function enableUI(enabled) {
        [els.collection, els.load, els.clear, els.loadDoc, els.docId, els.pageSize, els.importStructure].forEach((el) => { if (el) el.disabled = !enabled; });
        if (!enabled) [els.prev, els.next, els.viewStructure, els.createDoc, els.deleteStructure].forEach((el) => { if (el) el.disabled = true; });
    }

    function installStylesAndModals() {
        if (!$('bomff-runtime-modal-style')) {
            const style = document.createElement('style');
            style.id = 'bomff-runtime-modal-style';
            style.textContent = `
                #bomff-results-table{border-collapse:separate;border-spacing:0;}#bomff-results-table th{position:static;background:#fff;z-index:auto;}#bomff-results-table td{vertical-align:middle;}#bomff-results-table tbody tr:hover{background:#f6f7f7;}
                .bomff-editable-cell{cursor:cell;position:relative;outline:1px solid transparent;max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;transition:all .12s ease;}.bomff-editable-cell:hover{background:#eef6ff!important;outline:1px solid #72aee6;box-shadow:inset 0 0 0 1px #72aee6;}.bomff-editable-cell:hover:after{content:'✚';position:absolute;right:6px;top:50%;transform:translateY(-50%);font-size:11px;color:#2271b1;background:#eef6ff;}
                .bomff-runtime-modal{display:none;position:fixed;z-index:100000;inset:0;background:rgba(0,0,0,.45);}.bomff-runtime-modal.is-open{display:block;}.bomff-runtime-panel{background:#fff;max-width:1040px;margin:5vh auto;border-radius:8px;box-shadow:0 15px 45px rgba(0,0,0,.25);overflow:hidden;max-height:88vh;display:flex;flex-direction:column;}.bomff-runtime-panel--small{max-width:640px;}.bomff-runtime-header,.bomff-runtime-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;border-bottom:1px solid #ddd;}.bomff-runtime-footer{border-top:1px solid #ddd;border-bottom:0;justify-content:flex-end;}.bomff-runtime-header h2{margin:0;font-size:18px;}.bomff-runtime-body{padding:18px;overflow:auto;}.bomff-runtime-meta{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:14px;color:#555;}
                .bomff-edit-fields-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin-bottom:18px;}.bomff-edit-field-card{border:1px solid #dcdcde;border-radius:6px;padding:12px;background:#fff;}.bomff-edit-field-card label{display:flex;justify-content:space-between;gap:10px;font-weight:600;margin-bottom:8px;}.bomff-edit-field-type{font-weight:400;color:#777;font-size:12px;}.bomff-edit-field-card input[type=text],.bomff-edit-field-card input[type=number],.bomff-edit-field-card input[type=date],.bomff-edit-field-card textarea{width:100%;box-sizing:border-box;}.bomff-edit-field-card textarea{min-height:90px;font-family:Consolas,Monaco,monospace;font-size:13px;}
                .bomff-array-editor{display:flex;flex-direction:column;gap:8px;}.bomff-array-row{display:grid;grid-template-columns:1fr auto auto auto;gap:6px;align-items:start;}.bomff-array-row textarea{width:100%;min-height:44px;}.bomff-runtime-textarea{width:100%;min-height:300px;font-family:Consolas,Monaco,monospace;font-size:13px;line-height:1.45;}.bomff-runtime-textarea--small{min-height:180px;}.bomff-runtime-error{display:none;color:#b32d2e;margin-top:10px;}.bomff-hidden-force{display:none!important;}
            `;
            document.head.appendChild(style);
        }
        if (!$('bomff-edit-document-modal')) {
            document.body.insertAdjacentHTML('beforeend', `<div id="bomff-edit-document-modal" class="bomff-runtime-modal" aria-hidden="true"><div class="bomff-runtime-panel" role="dialog" aria-modal="true"><div class="bomff-runtime-header"><h2 id="bomff-edit-title">Edit document</h2><button class="button" type="button" data-close-modal="bomff-edit-document-modal">✕</button></div><div class="bomff-runtime-body"><div class="bomff-runtime-meta"><div><strong>Collection:</strong> <code id="bomff-edit-collection"></code></div><div><strong>Doc ID:</strong> <code id="bomff-edit-docid"></code></div></div><div id="bomff-edit-fields" class="bomff-edit-fields-grid"></div><p><button id="bomff-toggle-json" class="button" type="button">Advanced JSON</button></p><div id="bomff-json-wrap" class="bomff-hidden-force"><textarea id="bomff-edit-json" class="bomff-runtime-textarea" spellcheck="false"></textarea></div><div id="bomff-edit-error" class="bomff-runtime-error"></div></div><div class="bomff-runtime-footer"><button class="button" type="button" data-close-modal="bomff-edit-document-modal">Cancel</button><button id="bomff-save-edit" class="button button-primary" type="button">Save changes</button></div></div></div>`);
        }
        if (!$('bomff-field-modal')) {
            document.body.insertAdjacentHTML('beforeend', `<div id="bomff-field-modal" class="bomff-runtime-modal" aria-hidden="true"><div class="bomff-runtime-panel bomff-runtime-panel--small" role="dialog" aria-modal="true"><div class="bomff-runtime-header"><h2>Quick field edit</h2><button class="button" type="button" data-close-modal="bomff-field-modal">✕</button></div><div class="bomff-runtime-body"><div class="bomff-runtime-meta"><div><strong>Doc ID:</strong> <code id="bomff-field-docid"></code></div><div><strong>Field:</strong> <code id="bomff-field-name"></code></div><div><strong>Type:</strong> <code id="bomff-field-type"></code></div></div><div id="bomff-field-input"></div><div id="bomff-field-error" class="bomff-runtime-error"></div></div><div class="bomff-runtime-footer"><button class="button" type="button" data-close-modal="bomff-field-modal">Cancel</button><button id="bomff-save-field" class="button button-primary" type="button">Save field</button></div></div></div>`);
        }
        document.querySelectorAll('[data-close-modal]').forEach((btn) => { if (!btn.dataset.bound) { btn.dataset.bound = '1'; btn.addEventListener('click', () => closeModal(btn.dataset.closeModal)); } });
        if (!$('bomff-save-edit')?.dataset.bound) { $('bomff-save-edit').dataset.bound = '1'; $('bomff-save-edit').addEventListener('click', saveEditModal); }
        if (!$('bomff-save-field')?.dataset.bound) { $('bomff-save-field').dataset.bound = '1'; $('bomff-save-field').addEventListener('click', saveFieldModal); }
        if (!$('bomff-toggle-json')?.dataset.bound) { $('bomff-toggle-json').dataset.bound = '1'; $('bomff-toggle-json').addEventListener('click', () => { syncVisualToJson(); $('bomff-json-wrap').classList.toggle('bomff-hidden-force'); }); }
    }

    function modalError(id, msg) { const el = $(id); if (!el) return; el.textContent = msg || ''; el.style.display = msg ? 'block' : 'none'; }
    const openModal = (id) => $(id)?.classList.add('is-open');
    const closeModal = (id) => $(id)?.classList.remove('is-open');
    const inputValue = (value) => value === null || value === undefined ? '' : (typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value));

    function parseValue(raw, type) {
        if (type === 'number') { const n = Number(raw); if (Number.isNaN(n)) throw new Error('Invalid number.'); return n; }
        if (type === 'boolean') return raw === true || raw === 'true' || raw === '1';
        if (type === 'array' || type === 'map' || type === 'timestamp' || type === 'null') return String(raw).trim() === '' ? null : JSON.parse(raw);
        return raw;
    }

    function arrayEditorHtml(field, arr, readOnly) {
        const rows = (arr || []).map((item) => `<div class="bomff-array-row"><textarea class="bomff-array-value" ${readOnly ? 'disabled' : ''}>${esc(typeof item === 'object' ? JSON.stringify(item, null, 2) : String(item ?? ''))}</textarea><button class="button bomff-array-duplicate" type="button" ${readOnly ? 'disabled' : ''}>Duplicate</button><button class="button bomff-array-delete" type="button" ${readOnly ? 'disabled' : ''}>Delete</button><button class="button bomff-array-up" type="button" ${readOnly ? 'disabled' : ''}>↑</button></div>`).join('');
        return `<div class="bomff-array-editor" data-field="${esc(field)}">${rows}<button class="button bomff-array-add" type="button" ${readOnly ? 'disabled' : ''}>Add item</button></div>`;
    }

    function fieldHtml(name, value, readOnly) {
        const type = typeOf(value, name);
        const disabled = readOnly ? 'disabled' : '';
        let input = '';
        if (type === 'boolean') input = `<label style="justify-content:flex-start;font-weight:400;"><input class="bomff-field-input" data-field="${esc(name)}" data-type="boolean" type="checkbox" ${value ? 'checked' : ''} ${disabled}> true / enabled</label>`;
        else if (type === 'number') input = `<input class="bomff-field-input" data-field="${esc(name)}" data-type="number" type="number" step="any" value="${esc(value)}" ${disabled}>`;
        else if (type === 'date-string') input = `<input class="bomff-field-input" data-field="${esc(name)}" data-type="date-string" type="date" value="${esc(String(value || '').slice(0, 10))}" ${disabled}>`;
        else if (type === 'array') input = arrayEditorHtml(name, value, readOnly);
        else if (type === 'map' || type === 'timestamp' || type === 'null') input = `<textarea class="bomff-field-input" data-field="${esc(name)}" data-type="${esc(type)}" ${disabled}>${esc(inputValue(value))}</textarea>`;
        else input = `<textarea class="bomff-field-input" data-field="${esc(name)}" data-type="string" ${disabled}>${esc(inputValue(value))}</textarea>`;
        return `<div class="bomff-edit-field-card"><label><span>${esc(name)}</span><span class="bomff-edit-field-type">${esc(type)}</span></label>${input}</div>`;
    }

    function bindArrayButtons() {
        document.querySelectorAll('.bomff-array-editor').forEach((editor) => {
            if (editor.dataset.bound) return;
            editor.dataset.bound = '1';
            editor.addEventListener('click', (event) => {
                const target = event.target;
                if (target.classList.contains('bomff-array-add')) { const row = document.createElement('div'); row.className = 'bomff-array-row'; row.innerHTML = '<textarea class="bomff-array-value"></textarea><button class="button bomff-array-duplicate" type="button">Duplicate</button><button class="button bomff-array-delete" type="button">Delete</button><button class="button bomff-array-up" type="button">↑</button>'; target.before(row); }
                if (target.classList.contains('bomff-array-delete')) target.closest('.bomff-array-row')?.remove();
                if (target.classList.contains('bomff-array-duplicate')) target.closest('.bomff-array-row')?.after(target.closest('.bomff-array-row').cloneNode(true));
                if (target.classList.contains('bomff-array-up')) { const row = target.closest('.bomff-array-row'); if (row?.previousElementSibling?.classList.contains('bomff-array-row')) row.parentNode.insertBefore(row, row.previousElementSibling); }
                syncVisualToJson();
            });
            editor.addEventListener('input', syncVisualToJson);
        });
    }

    function readArrayEditor(editor) { return Array.from(editor.querySelectorAll('.bomff-array-value')).map((textarea) => { const raw = textarea.value.trim(); if (raw === '') return ''; try { return JSON.parse(raw); } catch (e) { return raw; } }); }

    function renderVisualFields(data, readOnly = false) {
        const keys = Object.keys(data || {}).sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }));
        $('bomff-edit-fields').innerHTML = keys.length ? keys.map((key) => fieldHtml(key, data[key], readOnly)).join('') : '<p class="description">This document has no fields.</p>';
        bindArrayButtons();
        document.querySelectorAll('.bomff-field-input').forEach((input) => ['input', 'change'].forEach((eventName) => input.addEventListener(eventName, syncVisualToJson)));
    }

    function collectVisual() {
        const out = {};
        document.querySelectorAll('#bomff-edit-fields .bomff-field-input').forEach((input) => { const field = input.dataset.field; const type = input.dataset.type; const raw = input.type === 'checkbox' ? input.checked : input.value; out[field] = type === 'date-string' ? raw : parseValue(raw, type); });
        document.querySelectorAll('#bomff-edit-fields .bomff-array-editor').forEach((editor) => { out[editor.dataset.field] = readArrayEditor(editor); });
        return out;
    }

    function syncVisualToJson() { if (!editState || editState.readOnly) return; try { $('bomff-edit-json').value = JSON.stringify(collectVisual(), null, 2); modalError('bomff-edit-error', ''); } catch (e) { modalError('bomff-edit-error', `Invalid value: ${e.message}`); } }
    function syncJsonToVisual() { const parsed = JSON.parse($('bomff-edit-json').value || '{}'); renderVisualFields(parsed, false); return parsed; }

    function columnsFromDocs(docs) { const set = new Set(); docs.forEach((doc) => Object.keys(doc.data || {}).forEach((key) => set.add(key))); return Array.from(set).sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' })).slice(0, 12); }

    function renderTable(docs) {
        lastDocs = docs || [];
        if (!lastDocs.length) { els.head.innerHTML = '<th>Doc ID</th><th>Fields</th><th class="bomff-col-actions">Actions</th>'; els.body.innerHTML = '<tr><td colspan="3" class="bomff-center-muted">No documents found.</td></tr>'; return; }
        const columns = columnsFromDocs(lastDocs); const used = new Set(columns);
        els.head.innerHTML = `<th>Doc ID</th>${columns.map((col) => `<th>${esc(col)}</th>`).join('')}<th>Other fields</th><th class="bomff-col-actions">Actions</th>`;
        els.body.innerHTML = lastDocs.map((doc) => { const data = doc.data || {}; const other = Object.keys(data).filter((key) => !used.has(key)); return `<tr data-doc-id="${esc(doc.id)}"><td><code>${esc(doc.id)}</code></td>${columns.map((col) => `<td class="bomff-editable-cell" title="Double-click to edit" data-doc-id="${esc(doc.id)}" data-field="${esc(col)}">${esc(compact(data[col]))}</td>`).join('')}<td>${other.length ? esc(other.join(', ')) : '—'}</td><td class="bomff-col-actions"><button class="button button-small" data-action="view" data-doc-id="${esc(doc.id)}">View</button> <button class="button button-small" data-action="edit" data-doc-id="${esc(doc.id)}">Edit</button> <button class="button button-small" data-action="delete" data-doc-id="${esc(doc.id)}">Delete</button></td></tr>`; }).join('');
    }

    async function loadCollection(pageToken = '') {
        const collection = (els.collection?.value || '').trim();
        if (!collection) return showMsg('Enter a collection name.', false);
        currentCollection = collection; currentPageToken = pageToken || '';
        try { showMsg(`Loading “${collection}”…`); const data = await ajax('bomff_list_documents', { collection, pageSize: els.pageSize?.value || 25, pageToken: currentPageToken }); nextPageToken = data.nextPageToken || ''; renderTable(data.documents || []); if (els.prev) els.prev.disabled = pageStack.length === 0; if (els.next) els.next.disabled = !nextPageToken; localStorage.setItem('bomff_last_collection', collection); showMsg(`Loaded ${(data.documents || []).length} document(s).`); }
        catch (e) { console.error(e); showMsg(e.message || 'Could not load documents.', false); }
    }

    async function getDoc(docId) { const cached = lastDocs.find((doc) => doc.id === docId); if (cached) return cached; return (await ajax('bomff_get_document', { collection: currentCollection, docId })).document; }

    async function openDocModal(docId, readOnly = false) {
        try { const doc = await getDoc(docId); editState = { docId, readOnly }; $('bomff-edit-title').textContent = readOnly ? 'View document' : 'Edit document'; $('bomff-edit-collection').textContent = currentCollection; $('bomff-edit-docid').textContent = docId; $('bomff-edit-json').value = JSON.stringify(doc.data || {}, null, 2); $('bomff-json-wrap').classList.add('bomff-hidden-force'); $('bomff-save-edit').style.display = readOnly ? 'none' : ''; renderVisualFields(doc.data || {}, readOnly); modalError('bomff-edit-error', ''); openModal('bomff-edit-document-modal'); }
        catch (e) { showMsg(e.message || 'Could not load document.', false); }
    }

    async function saveEditModal() {
        if (!editState || editState.readOnly) return;
        let parsed; try { parsed = $('bomff-json-wrap').classList.contains('bomff-hidden-force') ? collectVisual() : syncJsonToVisual(); } catch (e) { return modalError('bomff-edit-error', `Invalid data: ${e.message}`); }
        try { await ajax('bomff_save_document', { collection: currentCollection, docId: editState.docId, data: JSON.stringify(parsed) }); closeModal('bomff-edit-document-modal'); showMsg('Document saved.'); await loadCollection(currentPageToken); }
        catch (e) { modalError('bomff-edit-error', e.message || 'Could not save document.'); }
    }

    async function openFieldModal(docId, field) {
        try { const doc = await getDoc(docId); const value = (doc.data || {})[field]; const type = typeOf(value, field); fieldEditState = { docId, field, type, docData: doc.data || {} }; $('bomff-field-docid').textContent = docId; $('bomff-field-name').textContent = field; $('bomff-field-type').textContent = type; $('bomff-field-input').innerHTML = fieldHtml(field, value, false); bindArrayButtons(); modalError('bomff-field-error', ''); openModal('bomff-field-modal'); }
        catch (e) { showMsg(e.message || 'Could not load field.', false); }
    }

    async function saveFieldModal() {
        if (!fieldEditState) return;
        try { let value; const editor = $('bomff-field-input').querySelector('.bomff-array-editor'); if (editor) value = readArrayEditor(editor); else { const input = $('bomff-field-input').querySelector('.bomff-field-input'); value = input.dataset.type === 'date-string' ? input.value : parseValue(input.type === 'checkbox' ? input.checked : input.value, input.dataset.type); } const nextData = { ...fieldEditState.docData, [fieldEditState.field]: value }; await ajax('bomff_save_document', { collection: currentCollection, docId: fieldEditState.docId, data: JSON.stringify(nextData) }); closeModal('bomff-field-modal'); showMsg('Field saved.'); await loadCollection(currentPageToken); }
        catch (e) { modalError('bomff-field-error', e.message || 'Could not save field.'); }
    }

    async function deleteDoc(docId) { if (!confirm(`Delete document “${docId}”?`)) return; try { await ajax('bomff_delete_document', { collection: currentCollection, docId }); showMsg('Document deleted.'); await loadCollection(currentPageToken); } catch (e) { showMsg(e.message || 'Could not delete document.', false); } }

    function inferStructureFromDocs(docs) {
        const fields = new Map();
        docs.forEach((doc) => Object.entries(doc.data || {}).forEach(([key, value]) => { if (!fields.has(key)) fields.set(key, { name: key, type: typeOf(value, key), required: false, auto: false }); }));
        return Array.from(fields.values()).sort((a, b) => a.name.localeCompare(b.name));
    }

    async function importStructure() {
        if (!currentCollection || !lastDocs.length) return showStructureMsg('Load a collection first.', false);
        const fields = inferStructureFromDocs(lastDocs);
        if (!fields.length) return showStructureMsg('No fields detected.', false);
        try { const data = await ajax('bomff_save_structure', { collection: currentCollection, fields: JSON.stringify(fields) }); activeStructure = data.structure; showStructureMsg(`Structure saved for “${currentCollection}”.`); updateStructureButtons(); }
        catch (e) { showStructureMsg(e.message || 'Could not save structure.', false); }
    }

    async function loadStructure() {
        try { const data = await ajax('bomff_get_structure', {}); activeStructure = data.structure && Object.keys(data.structure).length ? data.structure : null; if (activeStructure) showStructureMsg(`Active structure: ${activeStructure.collection}`); else showStructureMsg('No active structure.', false); updateStructureButtons(); }
        catch (e) { showStructureMsg(e.message || 'Could not load structure.', false); }
    }

    function updateStructureButtons() {
        const has = !!activeStructure;
        if (els.viewStructure) els.viewStructure.disabled = !has;
        if (els.deleteStructure) els.deleteStructure.disabled = !has;
        if (els.createDoc) els.createDoc.disabled = !has;
    }

    async function deleteStructure() {
        if (!confirm('Delete active structure?')) return;
        try { await ajax('bomff_delete_structure', {}); activeStructure = null; showStructureMsg('Structure deleted.'); updateStructureButtons(); }
        catch (e) { showStructureMsg(e.message || 'Could not delete structure.', false); }
    }

    function viewStructure() {
        if (!activeStructure) return showStructureMsg('No active structure.', false);
        alert(JSON.stringify(activeStructure, null, 2));
    }

    function structureToData(structure) {
        const out = {};
        (structure.fields || []).forEach((field) => {
            if (field.auto) return;
            out[field.name] = defaultValueForType(field.type || 'string');
        });
        return out;
    }

    async function createDocumentFromStructure() {
        if (!activeStructure) return showStructureMsg('No active structure.', false);
        const docId = prompt('New document ID:');
        if (!docId) return;
        const collection = activeStructure.collection || (els.collection?.value || '').trim();
        if (!collection) return showStructureMsg('No collection selected.', false);
        currentCollection = collection;
        if (els.collection) els.collection.value = collection;
        editState = { docId, readOnly: false, isNew: true };
        const data = structureToData(activeStructure);
        $('bomff-edit-title').textContent = 'Create document';
        $('bomff-edit-collection').textContent = collection;
        $('bomff-edit-docid').textContent = docId;
        $('bomff-edit-json').value = JSON.stringify(data, null, 2);
        $('bomff-json-wrap').classList.add('bomff-hidden-force');
        $('bomff-save-edit').style.display = '';
        renderVisualFields(data, false);
        modalError('bomff-edit-error', '');
        openModal('bomff-edit-document-modal');
    }

    function bindEvents() {
        installStylesAndModals();
        els.load?.addEventListener('click', () => { pageStack = []; loadCollection(''); });
        els.clear?.addEventListener('click', () => { if (els.body) els.body.innerHTML = '<tr><td colspan="3" class="bomff-center-muted">Enter a collection and click “Load”.</td></tr>'; showMsg(''); });
        els.collection?.addEventListener('keydown', (event) => { if (event.key === 'Enter') { pageStack = []; loadCollection(''); } });
        els.next?.addEventListener('click', () => { if (nextPageToken) { pageStack.push(currentPageToken || ''); loadCollection(nextPageToken); } });
        els.prev?.addEventListener('click', () => loadCollection(pageStack.pop() || ''));
        els.loadDoc?.addEventListener('click', async () => { try { const docId = els.docId.value.trim(); const collection = els.collection.value.trim(); if (!docId || !collection) return showMsg('Enter collection and document ID.', false); const doc = (await ajax('bomff_get_document', { collection, docId })).document; currentCollection = collection; renderTable([doc]); showMsg('Loaded 1 document.'); } catch (e) { showMsg(e.message || 'Could not load document.', false); } });
        els.body?.addEventListener('click', (event) => { const btn = event.target.closest('button[data-action]'); if (!btn) return; if (btn.dataset.action === 'view') openDocModal(btn.dataset.docId, true); if (btn.dataset.action === 'edit') openDocModal(btn.dataset.docId, false); if (btn.dataset.action === 'delete') deleteDoc(btn.dataset.docId); });
        els.body?.addEventListener('dblclick', (event) => { const cell = event.target.closest('.bomff-editable-cell'); if (cell) openFieldModal(cell.dataset.docId, cell.dataset.field); });
        els.importStructure?.addEventListener('click', importStructure);
        els.viewStructure?.addEventListener('click', viewStructure);
        els.deleteStructure?.addEventListener('click', deleteStructure);
        els.createDoc?.addEventListener('click', createDocumentFromStructure);
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape') { closeModal('bomff-edit-document-modal'); closeModal('bomff-field-modal'); } });
    }

    async function init() {
        if (!els.collection) return;
        bindEvents();
        enableUI(false);
        const saved = localStorage.getItem('bomff_last_collection');
        if (saved) els.collection.value = saved;
        try { const status = await ajax('bomff_get_status', {}); if (!status.configured) { els.warning?.classList.remove('bomff-hidden'); return; } els.warning?.classList.add('bomff-hidden'); enableUI(true); await loadStructure(); if (saved && !didAutoload) { didAutoload = true; await loadCollection(''); } }
        catch (e) { console.error(e); els.warning?.classList.remove('bomff-hidden'); }
    }

    init();
});
