document.addEventListener('DOMContentLoaded', () => {
    const statusConnectionEl = document.getElementById('firebase-connection-status');
    const statusAuthEl = document.getElementById('firebase-auth-status');

    // ---------------------------------------------------------
    // UI text (single source of truth for copy)
    // ---------------------------------------------------------
    const UI_TEXT = {
        // Firebase/init
        configNotAvailable: 'Firebase configuration is not available.',
        configIncomplete: 'Firebase configuration is incomplete. Please check Settings.',
        initError: 'Could not initialize Firebase.',
        connected: 'Connected to Firebase',
        notAuthenticated: 'Not authenticated.',
        authenticatedAs: (email) => `Authenticated as ${email || 'unknown user'}.`,

        // Structure (guided creation)
        structureNone: 'No active structure. Import one from a collection to enable guided creation.',
        structureLoaded: 'Active structure loaded.',
        structureLoadError: (msg) => `Could not load the structure: ${msg}`,
        structureImportNeedCollection: 'Load a collection first (in the explorer) to import its structure.',
        structureAnalyzing: (col) => `Analyzing structure for “${col}”…`,
        structureNoDocs: 'No documents found to infer a structure.',
        structureImportError: (msg) => `Could not import structure: ${msg}`,
        structureSelectAtLeastOne: 'Select at least one field.',
        structureReplaceConfirmDifferent: (oldCol, newCol) =>
            `An active structure already exists for “${oldCol}”.\nDo you want to replace it with the structure from “${newCol}”?`,
        structureReplaceConfirmSame: 'A structure already exists for this collection. Replace it?',
        structureSaved: 'Structure saved.',
        structureSaveError: (msg) => `Could not save the structure: ${msg}`,
        structureDeleteConfirm: 'Delete the active structure? Guided document creation will be disabled.',
        structureDeleted: 'Structure deleted.',
        structureDeleteError: (msg) => `Could not delete the structure: ${msg}`,

        // Create document
        createNoStructure: 'No active structure.',
        createNeedLoadCollection: (col) =>
            `Load the “${col}” collection to create documents.`,
        createMissingRequired: (field) => `Missing required field: ${field}`,
        createInvalidNumber: (field) => `Invalid number in: ${field}`,
        createInvalidJson: (field) => `Invalid JSON in: ${field}`,
        createError: (msg) => `Could not create document: ${msg}`,

        // Explorer
        enterCollection: 'Enter a collection name.',
        loadCollectionFirst: 'Load a collection first.',
        enterDocId: 'Enter a Doc ID.',
        loadingCollection: (col) => `Loading “${col}”…`,
        noDocsOrNoPerm: 'No documents found (or insufficient permissions).',
        noResults: 'No results.',
        loadedCount: (n) => `Loaded ${n} document${n === 1 ? '' : 's'}.`,
        loadError: (msg) => `Could not load documents: ${msg}`,
        errorLoadingDocs: 'Error loading documents.',
        loadingDoc: (id) => `Loading document “${id}”…`,
        docNotFound: 'Document not found.',
        loadedOne: 'Loaded 1 document.',

        // JSON / Edit / Delete
        confirmDelete: (id) => `Are you sure you want to delete “${id}”?`,
        deleteError: (msg) => `Could not delete: ${msg}`,
        copyError: 'Could not copy to clipboard.',
        docLoadError: (msg) => `Could not load document: ${msg}`,
        jsonInvalid: (msg) => `Invalid JSON: ${msg}`,
        saveError: (msg) => `Could not save: ${msg}`,

        // Table placeholders
        tableLoading: 'Loading…',
        tableEmptyHint: 'Enter a collection name and click “Load”.',

        // Field helpers
        jsonPlaceholder: (type) => `JSON (${type})`,
        jsonHelp: (type) => `Enter valid JSON (${type}). Example: [] or {}.`,
    };

    // Firebase instances

    // ---------------------------
    // Auth UI (Email/Password)
    // ---------------------------
    const authEmailEl = document.getElementById('bomff-auth-email');
    const authPassEl = document.getElementById('bomff-auth-password');
    const btnAuthLogin = document.getElementById('bomff-auth-login');
    const btnAuthLogout = document.getElementById('bomff-auth-logout');

    const authOutBox = document.getElementById('bomff-auth-when-signed-out');
    const authInBox = document.getElementById('bomff-auth-when-signed-in');
    const authMsgOut = document.getElementById('bomff-auth-msg');
    const authMsgIn = document.getElementById('bomff-auth-msg-in');

    function setAuthUiSignedIn(user) {
        if (authOutBox) authOutBox.style.display = 'none';
        if (authInBox) authInBox.style.display = '';
        if (authMsgIn) authMsgIn.textContent = user?.email ? `Signed in as ${user.email}` : 'Signed in';
        if (authMsgOut) authMsgOut.textContent = '';
    }

    function setAuthUiSignedOut() {
        if (authOutBox) authOutBox.style.display = '';
        if (authInBox) authInBox.style.display = 'none';
        if (authMsgIn) authMsgIn.textContent = '';
    }

    async function doLoginEmailPassword() {
        const email = (authEmailEl?.value || '').trim();
        const pass = (authPassEl?.value || '').trim();

        if (!email || !pass) {
            if (authMsgOut) authMsgOut.textContent = 'Enter email and password.';
            return;
        }
        if (!auth) {
            if (authMsgOut) authMsgOut.textContent = 'Firebase is not initialized yet.';
            return;
        }

        try {
            if (btnAuthLogin) btnAuthLogin.disabled = true;
            if (authMsgOut) authMsgOut.textContent = 'Signing in…';
            await auth.signInWithEmailAndPassword(email, pass);
            if (authMsgOut) authMsgOut.textContent = '';
        } catch (e) {
            console.error(e);
            if (authMsgOut) authMsgOut.textContent = e?.message || 'Sign-in failed.';
        } finally {
            if (btnAuthLogin) btnAuthLogin.disabled = false;
        }
    }

    async function doLogout() {
        if (!auth) return;
        try {
            if (btnAuthLogout) btnAuthLogout.disabled = true;
            if (authMsgIn) authMsgIn.textContent = 'Signing out…';
            await auth.signOut();
        } catch (e) {
            console.error(e);
            if (authMsgIn) authMsgIn.textContent = e?.message || 'Sign-out failed.';
        } finally {
            if (btnAuthLogout) btnAuthLogout.disabled = false;
        }
    }

    if (btnAuthLogin) btnAuthLogin.addEventListener('click', doLoginEmailPassword);
    if (btnAuthLogout) btnAuthLogout.addEventListener('click', doLogout);
    if (authPassEl) {
        authPassEl.addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter') doLoginEmailPassword();
        });
    }

    function setConnectionStatus(text, ok = true) {
        if (!statusConnectionEl) return;
        statusConnectionEl.textContent = text;
        statusConnectionEl.className = ok ? 'bomff-status-success' : 'bomff-status-error';
    }

    function setAuthStatus(text, ok = true) {
        if (!statusAuthEl) return;
        statusAuthEl.textContent = text;
        statusAuthEl.className = ok ? 'bomff-status-success' : 'bomff-status-error';
    }

    if (!statusConnectionEl && !statusAuthEl) return;

	const firebaseConfig =
		(typeof window.bomffFirebaseConfig !== 'undefined' && window.bomffFirebaseConfig) ||
		null;

    if (!firebaseConfig) {
        setConnectionStatus(UI_TEXT.configNotAvailable, false);
        return;
    }

    const firebaseConfigSafe = firebaseConfig || {};
    if (!firebaseConfig.apiKey || !firebaseConfig.projectId) {
        setConnectionStatus(UI_TEXT.configIncomplete, false);
        return;
    }

    let app, db, auth;
    try {
        app = (firebase.apps && firebase.apps.length) ? firebase.app() : firebase.initializeApp(firebaseConfigSafe);
        db = firebase.firestore();
        auth = firebase.auth();
		
		window.fwpFirestore = window.fwpFirestore || {};
		window.fwpFirestore.db = db;
		window.fwpFirestore.auth = auth;	

        setConnectionStatus(UI_TEXT.connected, true);
    } catch (e) {
        console.error(e);
        setConnectionStatus(UI_TEXT.initError, false);
        return;
    }

    auth.setPersistence(firebase.auth.Auth.Persistence.LOCAL).catch(console.error);

    // Detect if we're on Firestore screen
    const collectionInputEl = document.getElementById('bomff-collection-name');
    const loadCollectionBtn = document.getElementById('bomff-load-collection');
    const resultsBodyEl = document.getElementById('bomff-collection-results');
    const isFirestorePage = !!(collectionInputEl && loadCollectionBtn && resultsBodyEl);

    // Structure UI (top bar)
    const btnImportStructure = document.getElementById('bomff-import-structure');
    const btnViewStructure = document.getElementById('bomff-view-structure');
    const btnCreateDoc = document.getElementById('bomff-create-doc');
    const btnDeleteStructure = document.getElementById('bomff-delete-structure');
    const structureMsgEl = document.getElementById('bomff-structure-msg');

    // Mini summary
    const structureMiniEl = document.getElementById('bomff-structure-mini');
    const structureCollectionEl = document.getElementById('bomff-structure-collection');
    const structureCountEl = document.getElementById('bomff-structure-count');

    // Modals
    const jsonModal = document.getElementById('bomff-json-modal');
    const jsonContent = document.getElementById('bomff-json-content');
    const btnJsonClose = document.getElementById('bomff-close-json');
    const btnJsonCloseX = document.getElementById('bomff-close-json-x');
    const btnJsonCopy = document.getElementById('bomff-copy-json');
    const btnJsonDownload = document.getElementById('bomff-download-json');

    const editModal = document.getElementById('bomff-edit-modal');
    const editCollectionEl = document.getElementById('bomff-edit-collection');
    const editDocIdEl = document.getElementById('bomff-edit-docid');
    const editJsonEl = document.getElementById('bomff-edit-json');
    const editErrorEl = document.getElementById('bomff-edit-error');
    const btnEditSave = document.getElementById('bomff-edit-save');
    const btnEditCancel = document.getElementById('bomff-edit-cancel');
    const btnEditCloseX = document.getElementById('bomff-edit-close-x');

    const structureModal = document.getElementById('bomff-structure-modal');
    const structureModalCollectionEl = document.getElementById('bomff-structure-modal-collection');
    const structureDetectedEl = document.getElementById('bomff-structure-detected');
    const btnStructureCancel = document.getElementById('bomff-structure-cancel');
    const btnStructureSave = document.getElementById('bomff-structure-save');
    const btnStructureCloseX = document.getElementById('bomff-structure-close-x');

    const structureViewModal = document.getElementById('bomff-structure-view-modal');
    const structureViewCollectionEl = document.getElementById('bomff-structure-view-collection');
    const structureViewFieldsEl = document.getElementById('bomff-structure-view-fields');
    const btnStructureViewClose = document.getElementById('bomff-structure-view-close');
    const btnStructureViewCloseX = document.getElementById('bomff-structure-view-close-x');

    const createModal = document.getElementById('bomff-create-modal');
    const createCollectionEl = document.getElementById('bomff-create-collection');
    const createFieldsEl = document.getElementById('bomff-create-fields');
    const createErrorEl = document.getElementById('bomff-create-error');
    const btnCreateSave = document.getElementById('bomff-create-save');
    const btnCreateCancel = document.getElementById('bomff-create-cancel');
    const btnCreateCloseX = document.getElementById('bomff-create-close-x');

    function setExplorerEnabled(enabled) {
        if (!isFirestorePage) return;
        [
            'bomff-collection-name',
            'bomff-load-collection',
            'bomff-clear-results',
            'bomff-prev-page',
            'bomff-next-page',
            'bomff-load-doc',
            'bomff-doc-id',
            'bomff-page-size'
        ].forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.disabled = !enabled;
        });

        if (btnImportStructure) btnImportStructure.disabled = !enabled;
    }

    // ---------------------------
    // Helpers AJAX (WP)
    // ---------------------------
    async function wpAjax(action, payload = {}) {
        if (!firebaseConfig.ajaxUrl) throw new Error('ajaxUrl not available');
        const fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', firebaseConfig.nonce || '');
        Object.entries(payload).forEach(([k, v]) => fd.append(k, v));

        const res = await fetch(firebaseConfig.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
        });

        const json = await res.json().catch(() => null);
        if (!json) throw new Error('Invalid server response');
        if (!json.success) throw new Error((json.data && json.data.message) ? json.data.message : 'AJAX error');
        return json.data;
    }

    function setStructureMsg(text, ok = true) {
        if (!structureMsgEl) return;
        structureMsgEl.textContent = text;
        structureMsgEl.style.color = ok ? 'green' : 'red';
    }

    function hide(el) {
        if (el) el.style.display = 'none';
    }

    function show(el) {
        if (el) el.style.display = '';
    }

    function closeModal(modalEl) {
        if (!modalEl) return;
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
    }

    function openModal(modalEl) {
        if (!modalEl) return;
        modalEl.style.display = 'block';
        modalEl.setAttribute('aria-hidden', 'false');
    }

    function escapeHtml(s) {
        return String(s)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function safeJsonStringify(obj) {
        return JSON.stringify(obj, (k, v) => {
            if (v && typeof v === 'object' && typeof v.seconds === 'number' && typeof v.nanoseconds === 'number') {
                return {
                    _type: 'timestamp',
                    seconds: v.seconds,
                    nanoseconds: v.nanoseconds
                };
            }
            return v;
        }, 2);
    }

    function reviveTimestamps(obj) {
        if (obj === null || typeof obj === 'undefined') return obj;
        if (Array.isArray(obj)) return obj.map(reviveTimestamps);
        if (typeof obj === 'object') {
            if (obj._type === 'timestamp' && typeof obj.seconds === 'number') {
                return new firebase.firestore.Timestamp(obj.seconds, obj.nanoseconds || 0);
            }
            const out = {};
            for (const [k, v] of Object.entries(obj)) out[k] = reviveTimestamps(v);
            return out;
        }
        return obj;
    }

    function safeJsonParse(text) {
        const obj = JSON.parse(text);
        return reviveTimestamps(obj);
    }

    // ---------------------------
    // Extension events (for addons)
    // ---------------------------
    function fwpEmit(name, detail) {
        try {
            document.dispatchEvent(new CustomEvent(name, {
                detail
            }));
        } catch (e) {}
    }

    function fwpCtx(base) {
        return Object.assign({
                ts: Date.now(),
                screen: 'firestore',
                projectId: firebaseConfig && firebaseConfig.projectId ? firebaseConfig.projectId : null,
                isPro: !!(firebaseConfig && firebaseConfig.isPro)
            },
            base || {}
        );
    }

	window.fwpEmit = window.fwpEmit || fwpEmit;
	window.fwpCtx  = window.fwpCtx  || fwpCtx;


    // ---------------------------
    // Structure
    // ---------------------------
    let activeStructure = null; // { collection, fields: [{name,type,required,auto}] }
    let currentCollection = null;

    function renderMiniStructure() {
        if (!structureMiniEl || !structureCollectionEl || !structureCountEl) return;

        if (!activeStructure || !activeStructure.collection || !Array.isArray(activeStructure.fields)) {
            hide(structureMiniEl);
            setStructureMsg(UI_TEXT.structureNone, false);

            if (btnCreateDoc) btnCreateDoc.disabled = true;
            if (btnDeleteStructure) btnDeleteStructure.disabled = true;
            if (btnViewStructure) btnViewStructure.disabled = true;
            return;
        }

        show(structureMiniEl);
        structureCollectionEl.textContent = activeStructure.collection;
        structureCountEl.textContent = String(activeStructure.fields.length);

        if (btnDeleteStructure) btnDeleteStructure.disabled = false;
        if (btnViewStructure) btnViewStructure.disabled = false;

        // Create only if it matches the loaded collection
        if (btnCreateDoc) {
            btnCreateDoc.disabled = !currentCollection || (currentCollection !== activeStructure.collection);
        }

        setStructureMsg(UI_TEXT.structureLoaded, true);
    }

    function renderStructureViewModal() {
        if (!structureViewCollectionEl || !structureViewFieldsEl) return;
        if (!activeStructure) return;

        structureViewCollectionEl.textContent = activeStructure.collection || '';
        structureViewFieldsEl.innerHTML = '';

        (activeStructure.fields || []).forEach((f) => {
            const row = document.createElement('div');
            row.className = 'bomff-structure-field';
            row.innerHTML = `
        <div class="bomff-grow">
          <div><code>${escapeHtml(f.name)}</code></div>
          <div class="bomff-muted">${escapeHtml(f.required ? 'required' : 'optional')}${f.auto ? ' · auto' : ''}</div>
        </div>
        <div class="bomff-pill">${escapeHtml(f.type || 'string')}</div>
      `;
            structureViewFieldsEl.appendChild(row);
        });
    }

    async function loadActiveStructureFromWP() {
        try {
            const data = await wpAjax('bomff_get_structure', {});
            activeStructure = data.structure && Object.keys(data.structure).length ? data.structure : null;
            renderMiniStructure();
        } catch (e) {
            console.error(e);
            setStructureMsg(UI_TEXT.structureLoadError(e.message), false);
        }
    }

    async function saveActiveStructureToWP(structure) {
        const payload = {
            collection: structure.collection,
            fields: JSON.stringify(structure.fields || [])
        };
        const data = await wpAjax('bomff_save_structure', payload);
        activeStructure = data.structure;
        renderMiniStructure();
    }

    async function deleteActiveStructureFromWP() {
        await wpAjax('bomff_delete_structure', {});
        activeStructure = null;
        renderMiniStructure();
    }

    function detectType(value) {
        if (value === null || typeof value === 'undefined') return 'null';
        if (Array.isArray(value)) return 'array';
        if (typeof value === 'string') return 'string';
        if (typeof value === 'number') return 'number';
        if (typeof value === 'boolean') return 'boolean';

        if (value && typeof value === 'object' && typeof value.seconds === 'number' && typeof value.nanoseconds === 'number') {
            return 'timestamp';
        }
        if (typeof value === 'object') return 'map';
        return 'string';
    }

    function isAutoTimestampField(fieldName, type) {
        const k = String(fieldName || '').toLowerCase();
        if (type !== 'timestamp') return false;
        return (k === 'createdat' || k === 'updatedat' || k.endsWith('_at'));
    }

    async function importStructureFromCollection(colName) {
        const limit = 25;
        const snap = await db
            .collection(colName)
            .orderBy(firebase.firestore.FieldPath.documentId())
            .limit(limit)
            .get();

        if (snap.empty) return [];

        const docs = snap.docs;
        const stats = new Map(); // field -> {count, types:{}}
        docs.forEach((d) => {
            const data = d.data() || {};
            Object.entries(data).forEach(([k, v]) => {
                if (!stats.has(k)) stats.set(k, {
                    count: 0,
                    types: {}
                });
                const st = stats.get(k);
                st.count += 1;
                const t = detectType(v);
                st.types[t] = (st.types[t] || 0) + 1;
            });
        });

        const fields = [];
        stats.forEach((st, name) => {
            const entries = Object.entries(st.types).sort((a, b) => b[1] - a[1]);
            const domType = entries.length ? entries[0][0] : 'string';
            const required = st.count >= Math.max(1, Math.ceil(docs.length * 0.8));
            const auto = isAutoTimestampField(name, domType);

            fields.push({
                name,
                type: domType === 'null' ? 'string' : domType,
                required,
                auto
            });
        });

        fields.sort((a, b) => {
            if (a.required !== b.required) return a.required ? -1 : 1;
            return a.name.localeCompare(b.name);
        });

        return fields;
    }

    let detectedFieldsBuffer = [];

    function renderDetectedFieldsForModal(fields) {
        if (!structureDetectedEl) return;
        structureDetectedEl.innerHTML = '';

        fields.forEach((f, idx) => {
            const row = document.createElement('div');
            row.className = 'bomff-structure-field';
            row.innerHTML = `
        <label style="display:flex; align-items:center; gap:10px; width:100%; margin:0;">
          <input type="checkbox" class="bomff-structure-include" data-idx="${idx}" checked />
          <div class="bomff-grow">
            <div><code>${escapeHtml(f.name)}</code></div>
            <div class="bomff-muted">
              <span class="bomff-pill" style="margin-right:6px;">${escapeHtml(f.type)}</span>
              ${f.required ? 'required' : 'optional'}
              ${f.auto ? ' · auto' : ''}
            </div>
          </div>
        </label>
      `;
            structureDetectedEl.appendChild(row);
        });
    }

    function getSelectedFieldsFromModal(originalFields) {
        const checks = structureDetectedEl ? structureDetectedEl.querySelectorAll('.bomff-structure-include') : [];
        const selected = [];
        checks.forEach((ch) => {
            if (!ch.checked) return;
            const idx = parseInt(ch.getAttribute('data-idx'), 10);
            const f = originalFields[idx];
            if (f) selected.push(f);
        });
        return selected;
    }

    // ---------------------------
    // Create document from structure
    // ---------------------------
    function buildCreateForm(fields) {
        if (!createFieldsEl) return;
        createFieldsEl.innerHTML = '';

        fields.forEach((f) => {
            if (f.auto) return;
            const wrap = document.createElement('div');
            wrap.className = 'bomff-field';

            const id = `bomff-create-${f.name}`;
            let inputHtml = '';

            if (f.type === 'number') {
                inputHtml = `<input type="number" id="${escapeHtml(id)}" data-type="number" data-name="${escapeHtml(f.name)}" />`;
            } else if (f.type === 'boolean') {
                inputHtml = `<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
          <input type="checkbox" id="${escapeHtml(id)}" data-type="boolean" data-name="${escapeHtml(f.name)}" />
          <span>${escapeHtml(f.name)} ${f.required ? '*' : ''}</span>
        </label>`;
            } else if (f.type === 'array' || f.type === 'map') {
                inputHtml = `<textarea id="${escapeHtml(id)}" rows="4" data-type="json" data-name="${escapeHtml(f.name)}" placeholder='${escapeHtml(UI_TEXT.jsonPlaceholder(f.type))}'></textarea>
          <div class="bomff-muted">${escapeHtml(UI_TEXT.jsonHelp(f.type))}</div>`;
            } else {
                inputHtml = `<input type="text" id="${escapeHtml(id)}" data-type="string" data-name="${escapeHtml(f.name)}" />`;
            }

            if (f.type !== 'boolean') {
                wrap.innerHTML = `
          <label for="${escapeHtml(id)}">${escapeHtml(f.name)}${f.required ? ' *' : ''}</label>
          ${inputHtml}
        `;
            } else {
                wrap.innerHTML = inputHtml;
            }

            createFieldsEl.appendChild(wrap);
        });
    }

    function setCreateError(text) {
        if (!createErrorEl) return;
        if (!text) {
            createErrorEl.style.display = 'none';
            createErrorEl.textContent = '';
            return;
        }
        createErrorEl.style.display = '';
        createErrorEl.textContent = text;
    }

    async function handleCreateDocument() {
        if (!activeStructure || !activeStructure.collection) {
            setCreateError(UI_TEXT.createNoStructure);
            return;
        }
        if (!currentCollection || currentCollection !== activeStructure.collection) {
            setCreateError(UI_TEXT.createNeedLoadCollection(activeStructure.collection));
            return;
        }

        const data = {};
        const inputs = createFieldsEl ? createFieldsEl.querySelectorAll('[data-name]') : [];
        for (const el of inputs) {
            const name = el.getAttribute('data-name');
            const type = el.getAttribute('data-type');
            const fieldDef = (activeStructure.fields || []).find((x) => x.name === name);
            const required = fieldDef && fieldDef.required;

            if (type === 'boolean') {
                data[name] = !!el.checked;
                continue;
            }

            const raw = (el.value || '').trim();

            if (required && !raw) {
                setCreateError(UI_TEXT.createMissingRequired(name));
                return;
            }

            if (!raw) continue;

            if (type === 'number') {
                const n = Number(raw);
                if (Number.isNaN(n)) {
                    setCreateError(UI_TEXT.createInvalidNumber(name));
                    return;
                }
                data[name] = n;
            } else if (type === 'json') {
                try {
                    data[name] = JSON.parse(raw);
                } catch {
                    setCreateError(UI_TEXT.createInvalidJson(name));
                    return;
                }
            } else {
                data[name] = raw;
            }
        }

        (activeStructure.fields || []).forEach((f) => {
            if (f.auto && f.type === 'timestamp') {
                data[f.name] = firebase.firestore.FieldValue.serverTimestamp();
            }
        });

        try {
            setCreateError('');
            btnCreateSave.disabled = true;

            fwpEmit('fwp:firestore:beforeWrite', fwpCtx({
                op: 'create',
                collection: currentCollection,
                docId: null,
                data: data
            }));

            const ref = await db.collection(currentCollection).add(data);

            fwpEmit('fwp:firestore:afterWrite', fwpCtx({
                op: 'create',
                collection: currentCollection,
                docId: ref && ref.id ? ref.id : null,
                ok: true
            }));

            closeModal(createModal);
            await loadPage('init');
        } catch (e) {
            console.error(e);

            fwpEmit('fwp:firestore:afterWrite', fwpCtx({
                op: 'create',
                collection: currentCollection,
                docId: null,
                ok: false,
                error: e && e.message ? e.message : String(e)
            }));


            setCreateError(UI_TEXT.createError(e.message));
        } finally {
            btnCreateSave.disabled = false;
        }
    }

    // ---------------------------
    // Explorer
    // ---------------------------
    function isLikelyNoisyField(key) {
        const k = key.toLowerCase();
        return (
            k.includes('descripcion') ||
            k.includes('description') ||
            k.includes('html') ||
            k.includes('texto') ||
            k.includes('content')
        );
    }

    function isLikelyUrl(v) {
        return typeof v === 'string' && (v.startsWith('http://') || v.startsWith('https://'));
    }

    function compactValue(v) {
        if (v === null || typeof v === 'undefined') return '—';

        if (typeof v === 'string') {
            if (isLikelyUrl(v)) return 'URL';
            if (v.length > 40) return escapeHtml(v.slice(0, 40) + '…');
            return escapeHtml(v);
        }
        if (typeof v === 'number') return String(v);
        if (typeof v === 'boolean') return v ? 'Yes' : 'No';
        if (Array.isArray(v)) return `${v.length}`;

        if (v && typeof v === 'object' && typeof v.seconds === 'number') {
            const d = new Date(v.seconds * 1000);
            return d.toISOString().slice(0, 10);
        }

        if (typeof v === 'object') return 'Object';
        return escapeHtml(String(v));
    }

    function clearTable(message) {
        if (!resultsBodyEl) return;
        resultsBodyEl.innerHTML = `
      <tr>
        <td colspan="3" style="text-align:center;color:#666;">
          ${escapeHtml(message)}
        </td>
      </tr>
    `;
    }

    function openJsonModal(data) {
        if (!jsonModal || !jsonContent) return;
        jsonContent.textContent = safeJsonStringify(data);
        openModal(jsonModal);
    }

    let currentColumns = [];
    let showExtrasColumn = true;

    function detectColumnsFromDocs(docs, maxCols = 7) {
        const freq = new Map();
        docs.forEach((doc) => {
            const data = doc.data() || {};
            Object.keys(data).forEach((k) => {
                if (isLikelyNoisyField(k)) return;
                freq.set(k, (freq.get(k) || 0) + 1);
            });
        });

        return [...freq.entries()]
            .sort((a, b) => b[1] - a[1])
            .slice(0, maxCols)
            .map(([k]) => k);
    }

    function renderExtrasCell(data, usedSet) {
        const keys = Object.keys(data || {}).filter((k) => !usedSet.has(k));
        if (!keys.length) return '—';
        const chips = keys.slice(0, 6).map((k) => `<span class="bomff-chip" title="${escapeHtml(k)}">${escapeHtml(k)}</span>`);
        const more = keys.length > 6 ? `<span class="bomff-chip">+${keys.length - 6}</span>` : '';
        return chips.join('') + more;
    }

    async function deleteDoc(col, id) {
        const ok = confirm(UI_TEXT.confirmDelete(id));
        if (!ok) return;

        try {
            fwpEmit('fwp:firestore:beforeWrite', fwpCtx({
                op: 'delete',
                collection: col,
                docId: id
            }));

            await db.collection(col).doc(id).delete();

            fwpEmit('fwp:firestore:afterWrite', fwpCtx({
                op: 'delete',
                collection: col,
                docId: id,
                ok: true
            }));

            await loadPage('init');
        } catch (e) {
            fwpEmit('fwp:firestore:afterWrite', fwpCtx({
                op: 'delete',
                collection: col,
                docId: id,
                ok: false,
                error: e && e.message ? e.message : String(e)
            }));

            alert(UI_TEXT.deleteError(e.message));
        }
    }

    async function openEditModal(col, id) {
        if (!editModal || !editJsonEl || !editCollectionEl || !editDocIdEl) return;
        editErrorEl.style.display = 'none';
        editErrorEl.textContent = '';

        try {
            const doc = await db.collection(col).doc(id).get();
            if (!doc.exists) {
                alert(UI_TEXT.docNotFound);
                return;
            }
            editCollectionEl.textContent = col;
            editDocIdEl.textContent = id;
            editJsonEl.value = safeJsonStringify(doc.data() || {});
            editModal.dataset.collection = col;
            editModal.dataset.docid = id;
            openModal(editModal);
        } catch (e) {
            alert(UI_TEXT.docLoadError(e.message));
        }
    }

    async function saveEditModal() {
        if (!editModal) return;
        const col = editModal.dataset.collection;
        const id = editModal.dataset.docid;
        if (!col || !id) return;

        let obj;
        try {
            obj = safeJsonParse(editJsonEl.value || '{}');
        } catch (e) {
            editErrorEl.style.display = '';
            editErrorEl.textContent = UI_TEXT.jsonInvalid(e.message);
            return;
        }

        try {
            btnEditSave.disabled = true;

            fwpEmit('fwp:firestore:beforeWrite', fwpCtx({
                op: 'update',
                collection: col,
                docId: id,
                data: obj
            }));

            await db.collection(col).doc(id).set(obj, {
                merge: false
            });

            fwpEmit('fwp:firestore:afterWrite', fwpCtx({
                op: 'update',
                collection: col,
                docId: id,
                ok: true
            }));

            closeModal(editModal);
            await loadPage('init');
        } catch (e) {
            fwpEmit('fwp:firestore:afterWrite', fwpCtx({
                op: 'update',
                collection: col,
                docId: id,
                ok: false,
                error: e && e.message ? e.message : String(e)
            }));

            editErrorEl.style.display = '';
            editErrorEl.textContent = UI_TEXT.saveError(e.message);
        } finally {
            btnEditSave.disabled = false;
        }
    }

    function renderRows(docs) {
        if (!resultsBodyEl) return;
        resultsBodyEl.innerHTML = '';

        if (!currentColumns.length) {
            currentColumns = detectColumnsFromDocs(docs, 7);
            const headRow = document.getElementById('bomff-results-head-row');
            if (headRow) {
                const cols = currentColumns.map((c) => `<th>${escapeHtml(c)}</th>`).join('');
                headRow.innerHTML = `
          <th>Doc ID</th>
          ${cols}
          ${showExtrasColumn ? '<th>Other</th>' : ''}
          <th class="bomff-col-actions">Actions</th>
        `;
            }
        }

        docs.forEach((doc) => {
            const data = doc.data() || {};
            const used = new Set(currentColumns);

            const tds = [];
            tds.push(`<td><code>${escapeHtml(doc.id)}</code></td>`);

            currentColumns.forEach((field) => {
                tds.push(`<td>${compactValue(data[field])}</td>`);
            });

            if (showExtrasColumn) {
                tds.push(`<td>${renderExtrasCell(data, used)}</td>`);
            }

            tds.push(`
        <td class="bomff-col-actions">
          <button class="button bomff-view-json" type="button">View JSON</button>
          <button class="button bomff-edit-doc" type="button">Edit</button>
          <button class="button bomff-del-doc" type="button">Delete</button>
        </td>
      `);

            const tr = document.createElement('tr');
			tr.dataset.docId = doc.id;
            tr.innerHTML = tds.join('');

            const viewBtn = tr.querySelector('.bomff-view-json');
            if (viewBtn) viewBtn.addEventListener('click', () => openJsonModal(data));

            const editBtn = tr.querySelector('.bomff-edit-doc');
            if (editBtn) editBtn.addEventListener('click', () => openEditModal(currentCollection, doc.id));

            const delBtn = tr.querySelector('.bomff-del-doc');
            if (delBtn) delBtn.addEventListener('click', () => deleteDoc(currentCollection, doc.id));

            resultsBodyEl.appendChild(tr);
        });
    }

    const pageSizeSelect = document.getElementById('bomff-page-size');
    const prevBtn = document.getElementById('bomff-prev-page');
    const nextBtn = document.getElementById('bomff-next-page');
    const msgEl = document.getElementById('bomff-collection-msg');
    const docIdInput = document.getElementById('bomff-doc-id');
    const loadDocBtn = document.getElementById('bomff-load-doc');
    const clearBtn = document.getElementById('bomff-clear-results');

    let pageSize = 25;
    let lastVisibleDoc = null;
    let cursorStack = [];

    function setMsg(text, ok = true) {
        if (!msgEl) return;
        msgEl.textContent = text;
        msgEl.style.color = ok ? 'green' : 'red';
    }

    async function loadPage(direction = 'init') {
        if (!currentCollection) return;

        pageSize = parseInt(pageSizeSelect.value, 10) || 25;

        let query = db
            .collection(currentCollection)
            .orderBy(firebase.firestore.FieldPath.documentId())
            .limit(pageSize);

        if (direction === 'next' && lastVisibleDoc) {
            cursorStack.push(lastVisibleDoc);
            query = query.startAfter(lastVisibleDoc);
        }

        if (direction === 'prev' && cursorStack.length) {
            const prevCursor = cursorStack.pop();
            query = query.startAt(prevCursor);
        }

        setMsg(UI_TEXT.loadingCollection(currentCollection), true);

        try {
            const snap = await query.get();
            if (snap.empty) {
                clearTable(UI_TEXT.noDocsOrNoPerm);
                setMsg(UI_TEXT.noResults, true);
                prevBtn.disabled = cursorStack.length === 0;
                nextBtn.disabled = true;
                return;
            }

            lastVisibleDoc = snap.docs[snap.docs.length - 1];

            if (direction === 'init') currentColumns = [];
            renderRows(snap.docs);

            prevBtn.disabled = cursorStack.length === 0;
            nextBtn.disabled = snap.docs.length < pageSize;

            setMsg(UI_TEXT.loadedCount(snap.docs.length), true);
        } catch (e) {
            console.error(e);
            setMsg(UI_TEXT.loadError(e.message), false);
            clearTable(UI_TEXT.errorLoadingDocs);
        }

        // Enable Create only if it matches the active structure collection
        if (btnCreateDoc && activeStructure && activeStructure.collection) {
            btnCreateDoc.disabled = !(currentCollection === activeStructure.collection);
        }
    }

    // ---------------------------
    // Explorer events
    // ---------------------------
    if (loadCollectionBtn) {
        loadCollectionBtn.addEventListener('click', () => {
            const name = (collectionInputEl.value || '').trim();
            if (!name) {
                setMsg(UI_TEXT.enterCollection, false);
                return;
            }

            currentCollection = name;
			
			window.bomffFirebaseConfig = window.bomffFirebaseConfig || {};
			window.bomffFirebaseConfig.collectionPath = currentCollection;

            cursorStack = [];
            lastVisibleDoc = null;
            currentColumns = [];
            showExtrasColumn = true;

            clearTable(UI_TEXT.tableLoading);
            loadPage('init');

            if (btnCreateDoc && activeStructure && activeStructure.collection) {
                btnCreateDoc.disabled = !(currentCollection === activeStructure.collection);
            }
        });
    }

    if (nextBtn) nextBtn.addEventListener('click', () => loadPage('next'));
    if (prevBtn) prevBtn.addEventListener('click', () => loadPage('prev'));

    if (loadDocBtn && docIdInput) {
        loadDocBtn.addEventListener('click', async () => {
            const id = (docIdInput.value || '').trim();
            if (!currentCollection) {
                setMsg(UI_TEXT.loadCollectionFirst, false);
                return;
            }
            if (!id) {
                setMsg(UI_TEXT.enterDocId, false);
                return;
            }

            setMsg(UI_TEXT.loadingDoc(id), true);

            try {
                const doc = await db.collection(currentCollection).doc(id).get();
                if (!doc.exists) {
                    setMsg(UI_TEXT.docNotFound, false);
                    clearTable(UI_TEXT.docNotFound);
                    return;
                }

                currentColumns = detectColumnsFromDocs([doc], 7);
                renderRows([doc]);
                prevBtn.disabled = cursorStack.length === 0;
                nextBtn.disabled = true;

                setMsg(UI_TEXT.loadedOne, true);
            } catch (e) {
                console.error(e);
                setMsg(UI_TEXT.loadError(e.message), false);
            }
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            currentCollection = null;
            cursorStack = [];
            lastVisibleDoc = null;
            currentColumns = [];
            showExtrasColumn = true;

            const headRow = document.getElementById('bomff-results-head-row');
            if (headRow) {
                headRow.innerHTML = `
          <th>Doc ID</th>
          <th>Fields</th>
          <th class="bomff-col-actions">Actions</th>
        `;
            }
            clearTable(UI_TEXT.tableEmptyHint);
            if (msgEl) msgEl.textContent = '';
            if (collectionInputEl) collectionInputEl.value = '';
            if (docIdInput) docIdInput.value = '';
            if (prevBtn) prevBtn.disabled = true;
            if (nextBtn) nextBtn.disabled = true;

            if (btnCreateDoc) btnCreateDoc.disabled = true;
        });
    }

    // ---------------------------
    // Modal events (do NOT close on outside click)
    // ---------------------------
    function bindJsonClose() {
        if (btnJsonClose && jsonModal) btnJsonClose.addEventListener('click', () => closeModal(jsonModal));
        if (btnJsonCloseX && jsonModal) btnJsonCloseX.addEventListener('click', () => closeModal(jsonModal));

        if (btnJsonCopy && jsonContent) {
            btnJsonCopy.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(jsonContent.textContent || '');
                } catch {
                    alert(UI_TEXT.copyError);
                }
            });
        }
        if (btnJsonDownload && jsonContent) {
            btnJsonDownload.addEventListener('click', () => {
                const blob = new Blob([jsonContent.textContent || ''], {
                    type: 'application/json'
                });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'document.json';
                a.click();
            });
        }
    }

    function bindEditModal() {
        if (btnEditCancel && editModal) btnEditCancel.addEventListener('click', () => closeModal(editModal));
        if (btnEditCloseX && editModal) btnEditCloseX.addEventListener('click', () => closeModal(editModal));
        if (btnEditSave) btnEditSave.addEventListener('click', saveEditModal);
    }

    function bindStructureViewModal() {
        if (btnStructureViewClose && structureViewModal) btnStructureViewClose.addEventListener('click', () => closeModal(structureViewModal));
        if (btnStructureViewCloseX && structureViewModal) btnStructureViewCloseX.addEventListener('click', () => closeModal(structureViewModal));
        if (btnViewStructure) {
            btnViewStructure.addEventListener('click', () => {
                if (!activeStructure) return;
                renderStructureViewModal();
                openModal(structureViewModal);
            });
        }
    }

    // ---------------------------
    // Import structure (modal)
    // ---------------------------
    async function startImportStructure() {
        if (!currentCollection) {
            setStructureMsg(UI_TEXT.structureImportNeedCollection, false);
            return;
        }

        if (structureModalCollectionEl) structureModalCollectionEl.textContent = currentCollection;
        if (structureDetectedEl) structureDetectedEl.innerHTML = '';
        btnStructureSave.disabled = true;

        openModal(structureModal);

        try {
            setStructureMsg(UI_TEXT.structureAnalyzing(currentCollection), true);
            detectedFieldsBuffer = await importStructureFromCollection(currentCollection);

            if (!detectedFieldsBuffer.length) {
                setStructureMsg(UI_TEXT.structureNoDocs, false);
                closeModal(structureModal);
                return;
            }

            renderDetectedFieldsForModal(detectedFieldsBuffer);
            btnStructureSave.disabled = false;
        } catch (e) {
            console.error(e);
            setStructureMsg(UI_TEXT.structureImportError(e.message), false);
            closeModal(structureModal);
        }
    }

    async function confirmAndSaveStructure() {
        if (!currentCollection) return;

        const selected = getSelectedFieldsFromModal(detectedFieldsBuffer);
        if (!selected.length) {
            alert(UI_TEXT.structureSelectAtLeastOne);
            return;
        }

        if (activeStructure && activeStructure.collection && activeStructure.collection !== currentCollection) {
            const ok = confirm(UI_TEXT.structureReplaceConfirmDifferent(activeStructure.collection, currentCollection));
            if (!ok) return;
        } else if (activeStructure && activeStructure.collection === currentCollection) {
            const ok = confirm(UI_TEXT.structureReplaceConfirmSame);
            if (!ok) return;
        }

        try {
            btnStructureSave.disabled = true;
            await saveActiveStructureToWP({
                collection: currentCollection,
                fields: selected
            });
            closeModal(structureModal);

            if (btnCreateDoc) btnCreateDoc.disabled = !(currentCollection === activeStructure.collection);
            setStructureMsg(UI_TEXT.structureSaved, true);
        } catch (e) {
            alert(UI_TEXT.structureSaveError(e.message));
        } finally {
            btnStructureSave.disabled = false;
        }
    }

    function bindStructureModal() {
        if (btnImportStructure) btnImportStructure.addEventListener('click', startImportStructure);
        if (btnStructureCancel) btnStructureCancel.addEventListener('click', () => closeModal(structureModal));
        if (btnStructureCloseX) btnStructureCloseX.addEventListener('click', () => closeModal(structureModal));
        if (btnStructureSave) btnStructureSave.addEventListener('click', confirmAndSaveStructure);

        if (btnDeleteStructure) {
            btnDeleteStructure.addEventListener('click', async () => {
                const ok = confirm(UI_TEXT.structureDeleteConfirm);
                if (!ok) return;
                try {
                    await deleteActiveStructureFromWP();
                    if (btnCreateDoc) btnCreateDoc.disabled = true;
                    setStructureMsg(UI_TEXT.structureDeleted, true);
                } catch (e) {
                    alert(UI_TEXT.structureDeleteError(e.message));
                }
            });
        }
    }

    // ---------------------------
    // Create document modal
    // ---------------------------
    function openCreateDocModal() {
        if (!activeStructure || !activeStructure.collection) {
            setStructureMsg(UI_TEXT.createNoStructure, false);
            return;
        }
        if (!currentCollection || currentCollection !== activeStructure.collection) {
            setStructureMsg(UI_TEXT.createNeedLoadCollection(activeStructure.collection), false);
            return;
        }

        if (createCollectionEl) createCollectionEl.textContent = activeStructure.collection;
        setCreateError('');
        buildCreateForm(activeStructure.fields || []);
        openModal(createModal);
    }

    function bindCreateModal() {
        if (btnCreateDoc) btnCreateDoc.addEventListener('click', openCreateDocModal);
        if (btnCreateCancel) btnCreateCancel.addEventListener('click', () => closeModal(createModal));
        if (btnCreateCloseX) btnCreateCloseX.addEventListener('click', () => closeModal(createModal));
        if (btnCreateSave) btnCreateSave.addEventListener('click', handleCreateDocument);
    }

    // ---------------------------
    // Bind once + auth gate
    // ---------------------------
    function bindFirestoreOnce() {
        if (!isFirestorePage) return;
        if (bindFirestoreOnce._bound) return;
        bindFirestoreOnce._bound = true;

        bindJsonClose();
        bindEditModal();
        bindStructureModal();
        bindStructureViewModal();
        bindCreateModal();

        clearTable(UI_TEXT.tableEmptyHint);
    }

    auth.onAuthStateChanged(async (user) => {
        if (user) {
            setAuthStatus(UI_TEXT.authenticatedAs(user.email), true);
            setAuthUiSignedIn(user);
            setExplorerEnabled(true);
            bindFirestoreOnce();
            await loadActiveStructureFromWP();
        } else {
            setAuthStatus(UI_TEXT.notAuthenticated, false);
            setAuthUiSignedOut();
            setExplorerEnabled(false);
        }
    });

    setExplorerEnabled(false);
});