from pathlib import Path

p = Path('bomff-scripts.js')
s = p.read_text(encoding='utf-8')

s = s.replace(
".bomff-runtime-panel{background:#fff;max-width:1040px;",
".bomff-runtime-panel{background:#fff;max-width:820px;"
)

s = s.replace(
".bomff-edit-fields-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin-bottom:18px;}",
".bomff-edit-fields-grid{display:flex;flex-direction:column;gap:14px;margin-bottom:18px;}"
)

s = s.replace(
".bomff-array-editor{display:flex;flex-direction:column;gap:8px;}",
".bomff-field-presets{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;}.bomff-field-presets .button{min-height:26px;line-height:24px;padding:0 8px;font-size:12px;}.bomff-array-editor{display:flex;flex-direction:column;gap:8px;}"
)

old_field = """    function fieldHtml(name, value, readOnly) {
        const type = typeOf(value, name);
        const disabled = readOnly ? 'disabled' : '';
        let input = '';
        if (type === 'boolean') input = `<label style=\"justify-content:flex-start;font-weight:400;\"><input class=\"bomff-field-input\" data-field=\"${esc(name)}\" data-type=\"boolean\" type=\"checkbox\" ${value ? 'checked' : ''} ${disabled}> true / enabled</label>`;
        else if (type === 'number') input = `<input class=\"bomff-field-input\" data-field=\"${esc(name)}\" data-type=\"number\" type=\"number\" step=\"any\" value=\"${esc(value)}\" ${disabled}>`;
        else if (type === 'date-string') input = `<input class=\"bomff-field-input\" data-field=\"${esc(name)}\" data-type=\"date-string\" type=\"date\" value=\"${esc(String(value || '').slice(0, 10))}\" ${disabled}>`;
        else if (type === 'array') input = arrayEditorHtml(name, value, readOnly);
        else if (type === 'map' || type === 'timestamp' || type === 'null') input = `<textarea class=\"bomff-field-input\" data-field=\"${esc(name)}\" data-type=\"${esc(type)}\" ${disabled}>${esc(inputValue(value))}</textarea>`;
        else input = `<textarea class=\"bomff-field-input\" data-field=\"${esc(name)}\" data-type=\"string\" ${disabled}>${esc(inputValue(value))}</textarea>`;
        return `<div class=\"bomff-edit-field-card\"><label><span>${esc(name)}</span><span class=\"bomff-edit-field-type\">${esc(type)}</span></label>${input}</div>`;
    }
"""

new_field = """    function presetButtonsHtml(type, readOnly) {
        if (readOnly) return '';
        const presets = {
            string: [['Empty', 'empty'], ['Draft', 'draft'], ['Active', 'active'], ['Archived', 'archived']],
            'date-string': [['Today', 'today'], ['Empty', 'empty']],
            number: [['0', 'zero'], ['1', 'one'], ['+10%', 'plus10'], ['-10%', 'minus10']],
            boolean: [['True', 'true'], ['False', 'false']],
            array: [['Empty array', 'emptyArray']],
            map: [['Empty object', 'emptyObject']],
            timestamp: [['Now', 'timestampNow']],
            null: [['Null', 'null'], ['Empty', 'empty']],
        };
        const buttons = presets[type] || presets.string;
        return `<div class=\"bomff-field-presets\">${buttons.map(([label, preset]) => `<button class=\"button bomff-preset\" type=\"button\" data-preset=\"${esc(preset)}\">${esc(label)}</button>`).join('')}</div>`;
    }

    function fieldHtml(name, value, readOnly) {
        const type = typeOf(value, name);
        const disabled = readOnly ? 'disabled' : '';
        let input = '';
        if (type === 'boolean') input = `<label style=\"justify-content:flex-start;font-weight:400;\"><input class=\"bomff-field-input\" data-field=\"${esc(name)}\" data-type=\"boolean\" type=\"checkbox\" ${value ? 'checked' : ''} ${disabled}> true / enabled</label>`;
        else if (type === 'number') input = `<input class=\"bomff-field-input\" data-field=\"${esc(name)}\" data-type=\"number\" type=\"number\" step=\"any\" value=\"${esc(value)}\" ${disabled}>`;
        else if (type === 'date-string') input = `<input class=\"bomff-field-input\" data-field=\"${esc(name)}\" data-type=\"date-string\" type=\"date\" value=\"${esc(String(value || '').slice(0, 10))}\" ${disabled}>`;
        else if (type === 'array') input = arrayEditorHtml(name, value, readOnly);
        else if (type === 'map' || type === 'timestamp' || type === 'null') input = `<textarea class=\"bomff-field-input\" data-field=\"${esc(name)}\" data-type=\"${esc(type)}\" ${disabled}>${esc(inputValue(value))}</textarea>`;
        else input = `<textarea class=\"bomff-field-input\" data-field=\"${esc(name)}\" data-type=\"string\" ${disabled}>${esc(inputValue(value))}</textarea>`;
        return `<div class=\"bomff-edit-field-card\"><label><span>${esc(name)}</span><span class=\"bomff-edit-field-type\">${esc(type)}</span></label>${input}${presetButtonsHtml(type, readOnly)}</div>`;
    }
"""

s = s.replace(old_field, new_field)

s = s.replace(
"<button class=\"button button-small\" data-action=\"view\" data-doc-id=\"${esc(doc.id)}\">View</button>",
"<button class=\"button button-small\" data-action=\"duplicate\" data-doc-id=\"${esc(doc.id)}\">Duplicate</button>"
)

insert_after = """    async function saveFieldModal() {
        if (!fieldEditState) return;
        try { let value; const editor = $('bomff-field-input').querySelector('.bomff-array-editor'); if (editor) value = readArrayEditor(editor); else { const input = $('bomff-field-input').querySelector('.bomff-field-input'); value = input.dataset.type === 'date-string' ? input.value : parseValue(input.type === 'checkbox' ? input.checked : input.value, input.dataset.type); } const nextData = { ...fieldEditState.docData, [fieldEditState.field]: value }; await ajax('bomff_save_document', { collection: currentCollection, docId: fieldEditState.docId, data: JSON.stringify(nextData) }); closeModal('bomff-field-modal'); showMsg('Field saved.'); await loadCollection(currentPageToken); }
        catch (e) { modalError('bomff-field-error', e.message || 'Could not save field.'); }
    }
"""

dup_fn = insert_after + """

    function applyPresetValue(input, preset) {
        if (!input) return;
        const type = input.dataset.type;
        if (type === 'boolean') { input.checked = preset === 'true'; return; }
        if (type === 'number') {
            const current = Number(input.value || 0);
            if (preset === 'zero') input.value = 0;
            else if (preset === 'one') input.value = 1;
            else if (preset === 'plus10') input.value = Number.isFinite(current) ? (current * 1.1).toFixed(2).replace(/\\.00$/, '') : 0;
            else if (preset === 'minus10') input.value = Number.isFinite(current) ? (current * 0.9).toFixed(2).replace(/\\.00$/, '') : 0;
            return;
        }
        if (type === 'date-string') {
            if (preset === 'today') input.value = new Date().toISOString().slice(0, 10);
            else if (preset === 'empty') input.value = '';
            return;
        }
        if (preset === 'empty') input.value = '';
        else if (preset === 'draft') input.value = 'draft';
        else if (preset === 'active') input.value = 'active';
        else if (preset === 'archived') input.value = 'archived';
        else if (preset === 'emptyArray') input.value = '[]';
        else if (preset === 'emptyObject') input.value = '{}';
        else if (preset === 'timestampNow') input.value = JSON.stringify({ _type: 'timestamp', iso: new Date().toISOString() }, null, 2);
        else if (preset === 'null') input.value = 'null';
    }

    function handlePresetClick(event) {
        const btn = event.target.closest('.bomff-preset');
        if (!btn) return;
        const card = btn.closest('.bomff-edit-field-card');
        const input = card?.querySelector('.bomff-field-input');
        applyPresetValue(input, btn.dataset.preset);
        input?.dispatchEvent(new Event('change', { bubbles: true }));
        syncVisualToJson();
    }

    async function duplicateDoc(docId) {
        const newId = prompt(`Duplicate document “${docId}” as:`, `${docId}-copy`);
        if (!newId || newId === docId) return;
        try { const doc = await getDoc(docId); await ajax('bomff_save_document', { collection: currentCollection, docId: newId, data: JSON.stringify(doc.data || {}) }); showMsg(`Document duplicated as “${newId}”.`); await loadCollection(currentPageToken); }
        catch (e) { showMsg(e.message || 'Could not duplicate document.', false); }
    }
"""

s = s.replace(insert_after, dup_fn)

s = s.replace(
"if (!$('bomff-toggle-json')?.dataset.bound) { $('bomff-toggle-json').dataset.bound = '1'; $('bomff-toggle-json').addEventListener('click', () => { syncVisualToJson(); $('bomff-json-wrap').classList.toggle('bomff-hidden-force'); }); }",
"if (!$('bomff-toggle-json')?.dataset.bound) { $('bomff-toggle-json').dataset.bound = '1'; $('bomff-toggle-json').addEventListener('click', () => { syncVisualToJson(); $('bomff-json-wrap').classList.toggle('bomff-hidden-force'); }); }\n        if (!document.body.dataset.bomffPresetsBound) { document.body.dataset.bomffPresetsBound = '1'; document.body.addEventListener('click', handlePresetClick); }"
)

s = s.replace(
"if (btn.dataset.action === 'view') openDocModal(btn.dataset.docId, true); if (btn.dataset.action === 'edit') openDocModal(btn.dataset.docId, false); if (btn.dataset.action === 'delete') deleteDoc(btn.dataset.docId);",
"if (btn.dataset.action === 'duplicate') duplicateDoc(btn.dataset.docId); if (btn.dataset.action === 'edit') openDocModal(btn.dataset.docId, false); if (btn.dataset.action === 'delete') deleteDoc(btn.dataset.docId);"
)

p.write_text(s, encoding='utf-8')
print('Editor UX patch applied.')
