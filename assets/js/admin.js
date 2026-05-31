const WPAE_UI = {
visualNodeEditor: 'Визуальный редактор узлов',
variablesEditor: 'Визуальный редактор переменных',
emptyNodes: 'Узлы пока не добавлены.',
emptyDataList: 'Список пуст.',
nodeId: 'ID узла',
nodeType: 'Тип узла',
unknownNode: 'Неизвестный узел',
config: 'Конфигурация',
conditionGroup: 'Группа условий',
conditionRule: 'Правило условия',
comparison: 'Сравнение',
leftOperand: 'Левый операнд',
rightOperand: 'Правый операнд',
addRule: 'Добавить правило',
addGroup: 'Добавить группу',
moveUp: 'Вверх',
moveDown: 'Вниз',
remove: 'Удалить',
addNode: 'Добавить узел',
addField: 'Добавить поле',
addItem: 'Добавить элемент',
fieldKey: 'Ключ',
itemLabel: 'Элемент',
valueType: 'Тип значения',
trueLabel: 'Да',
falseLabel: 'Нет',
nullLabel: 'Значение null',
objectType: 'Объект',
arrayType: 'Массив',
stringType: 'Строка',
integerType: 'Целое число',
floatType: 'Число',
booleanType: 'Булево',
nullType: 'Null',
pathSource: 'Путь',
valueSource: 'Значение',
pathPlaceholder: 'variables.message',
loopPathPlaceholder: 'variables.orders',
defaultFieldKey: 'field',
};

const WPAE_FIELD_LABELS = {
scope: 'Область',
key: 'Ключ',
value: 'Значение',
hook: 'Имя хука',
payload: 'Данные',
event: 'Имя события',
condition: 'Условие',
on_true: 'Ветка "Да"',
on_false: 'Ветка "Нет"',
source: 'Источник',
item_name: 'Имя элемента',
nodes: 'Вложенные узлы',
};

const WPAE_ALL_DATA_TYPES = ['string', 'integer', 'float', 'boolean', 'null', 'object', 'array'];

let wpaeNodeSchemas = [];

function getNodeSchemas() {
if (!wpaeNodeSchemas.length) {
wpaeNodeSchemas = normalizeSchemas((window.wpaeAdminConfig && window.wpaeAdminConfig.schemas) || []);
}

return wpaeNodeSchemas;
}

document.addEventListener('DOMContentLoaded', function () {
initTriggerFields();
initVariableEditor();
initNodeEditor();
});

function initTriggerFields() {
const triggerSelect = document.getElementById('wp-automation-engine-trigger-type');
const triggerRows = document.querySelectorAll('.wpae-trigger-row');

if (!triggerSelect || !triggerRows.length) {
return;
}

const toggleTriggerRows = function () {
const triggerType = triggerSelect.value;

triggerRows.forEach(function (row) {
const allowedTypes = (row.getAttribute('data-trigger-types') || '').split(/\s+/);
const isVisible = allowedTypes.includes(triggerType);

row.classList.toggle('is-hidden', !isVisible);
});
};

triggerSelect.addEventListener('change', toggleTriggerRows);
toggleTriggerRows();
}

function initVariableEditor() {
const mount = document.getElementById('wpae-variable-editor');

if (!mount) {
return;
}

const targetInputId = mount.getAttribute('data-target-input') || '';
const textarea = document.getElementById(targetInputId);

if (!textarea) {
return;
}

const parsed = parseJsonInput(textarea.value, true);
const state = {
value: parsed.valid && isCompositeValue(parsed.value) ? parsed.value : {},
};

function syncTextarea() {
textarea.value = JSON.stringify(state.value, null, 2);
}

function render() {
mount.innerHTML = '';
mount.appendChild(createEditorMessage(WPAE_UI.variablesEditor, 'description'));
mount.appendChild(
createDataEditor(
state.value,
function (nextValue) {
state.value = isCompositeValue(nextValue) ? nextValue : {};
syncTextarea();
},
render,
{
allowedTypes: ['object', 'array'],
},
0
)
);
syncTextarea();
}

render();
}

function initNodeEditor() {
const mount = document.getElementById('wpae-node-editor');

if (!mount) {
return;
}

const targetInputId = mount.getAttribute('data-target-input') || '';
const textarea = document.getElementById(targetInputId);

if (!textarea) {
return;
}

const schemas = getNodeSchemas();
const schemaMap = {};
const state = {
nodes: [],
nodeCounter: 0,
};

schemas.forEach(function (schema) {
schemaMap[schema.type] = schema;
});

state.nodes = normalizeNodeList(parseNodeList(textarea.value, []), schemas, schemaMap, state);

function syncTextarea() {
textarea.value = JSON.stringify(state.nodes, null, 2);
}

function render() {
mount.innerHTML = '';
mount.appendChild(createEditorMessage(WPAE_UI.visualNodeEditor, 'description'));
renderNodeList(mount, state.nodes, 0);
syncTextarea();
}

function renderNodeList(container, nodes, depth) {
const list = document.createElement('div');
list.className = 'wpae-node-list';

if (!nodes.length) {
list.appendChild(createEditorMessage(WPAE_UI.emptyNodes, 'empty'));
}

nodes.forEach(function (node, index) {
list.appendChild(renderNodeCard(node, nodes, index, depth));
});

list.appendChild(createAddNodeControls(function (type) {
nodes.push(createDefaultNode(type, schemas, schemaMap, state));
render();
}));

container.appendChild(list);
}

function renderNodeCard(node, collection, index, depth) {
const schema = schemaMap[node.type] || null;
const card = document.createElement('div');
card.className = 'wpae-node-card';
card.setAttribute('data-depth', String(depth));

const header = document.createElement('div');
header.className = 'wpae-node-card__header';

const title = document.createElement('div');
title.className = 'wpae-node-card__title';

const titleText = document.createElement('strong');
titleText.textContent = schema ? schema.label || schema.type : WPAE_UI.unknownNode;
title.appendChild(titleText);

const typeCode = document.createElement('code');
typeCode.textContent = node.type || '';
title.appendChild(typeCode);

header.appendChild(title);
header.appendChild(createNodeActions(collection, index));
card.appendChild(header);

const body = document.createElement('div');
body.className = 'wpae-node-card__body';

body.appendChild(createTextField(WPAE_UI.nodeId, node.id || '', function (value) {
node.id = value;
syncTextarea();
}));

body.appendChild(createTypeField(node, function (value) {
const replacement = createDefaultNode(value, schemas, schemaMap, state);
replacement.id = node.id || replacement.id;
collection[index] = replacement;
render();
}));

if (!schema) {
body.appendChild(createFieldWithContent(WPAE_UI.config, createDataEditor(node.config || {}, function (value) {
node.config = ensureCompositeValue(value, {});
syncTextarea();
}, render, {
allowedTypes: ['object', 'array'],
hideLabel: true,
}, depth + 1)));
card.appendChild(body);
return card;
}

schema.fields.forEach(function (field) {
body.appendChild(renderConfigField(field, node.config, depth + 1));
});

card.appendChild(body);
return card;
}

function renderConfigField(field, config, depth) {
config = ensureObject(config);
const labelText = getFieldLabel(field);
const wrapper = document.createElement('div');
wrapper.className = 'wpae-field';

const label = document.createElement('label');
label.className = 'wpae-field__label';
label.textContent = labelText;
wrapper.appendChild(label);

switch (field.type) {
case 'text':
case 'path':
wrapper.appendChild(createTextControl(config, field.name, config[field.name] || '', field.type === 'path' ? WPAE_UI.loopPathPlaceholder : '', false));
break;
case 'select':
wrapper.appendChild(createSelectControl(config, field, config[field.name] || ''));
break;
case 'mixed':
wrapper.appendChild(createDataEditor(config[field.name], function (value) {
config[field.name] = value;
syncTextarea();
}, render, {
allowedTypes: WPAE_ALL_DATA_TYPES,
hideLabel: true,
}, depth));
break;
case 'object':
wrapper.appendChild(createDataEditor(config[field.name], function (value) {
config[field.name] = ensureCompositeValue(value, {});
syncTextarea();
}, render, {
allowedTypes: ['object', 'array'],
hideLabel: true,
}, depth));
break;
case 'condition':
config[field.name] = normalizeCondition(config[field.name]);
renderConditionEditor(wrapper, config[field.name], depth);
break;
case 'nodes':
config[field.name] = normalizeNodeList(config[field.name], schemas, schemaMap, state);
renderNodeList(wrapper, config[field.name], depth);
break;
default:
wrapper.appendChild(createDataEditor(config[field.name], function (value) {
config[field.name] = value;
syncTextarea();
}, render, {
allowedTypes: WPAE_ALL_DATA_TYPES,
hideLabel: true,
}, depth));
break;
}

return wrapper;
}

function renderConditionEditor(container, condition, depth) {
const conditionRoot = document.createElement('div');
conditionRoot.className = 'wpae-condition-builder';
renderConditionNode(conditionRoot, condition, null, null, depth);
container.appendChild(conditionRoot);
}

function renderConditionNode(container, condition, parentCollection, index, depth) {
const card = document.createElement('div');
card.className = 'wpae-condition-card';
card.setAttribute('data-depth', String(depth));

if (isConditionGroup(condition)) {
const groupHeader = document.createElement('div');
groupHeader.className = 'wpae-condition-card__header';

const title = document.createElement('strong');
title.textContent = WPAE_UI.conditionGroup;
groupHeader.appendChild(title);

if (parentCollection) {
groupHeader.appendChild(createRemoveButton(function () {
parentCollection.splice(index, 1);
render();
}));
}

card.appendChild(groupHeader);

const operatorField = document.createElement('div');
operatorField.className = 'wpae-field';
operatorField.appendChild(createSelectFromValues(['AND', 'OR'], condition.operator || 'AND', function (value) {
condition.operator = value;
syncTextarea();
}));
card.appendChild(operatorField);

const children = document.createElement('div');
children.className = 'wpae-condition-children';

condition.conditions.forEach(function (childCondition, childIndex) {
renderConditionNode(children, childCondition, condition.conditions, childIndex, depth + 1);
});

const actions = document.createElement('div');
actions.className = 'wpae-inline-actions';
actions.appendChild(createSimpleButton(WPAE_UI.addRule, function () {
condition.conditions.push(createDefaultConditionRule());
render();
}));
actions.appendChild(createSimpleButton(WPAE_UI.addGroup, function () {
condition.conditions.push(createDefaultConditionGroup());
render();
}));

card.appendChild(children);
card.appendChild(actions);
container.appendChild(card);
return;
}

const ruleHeader = document.createElement('div');
ruleHeader.className = 'wpae-condition-card__header';

const title = document.createElement('strong');
title.textContent = WPAE_UI.conditionRule;
ruleHeader.appendChild(title);

if (parentCollection) {
ruleHeader.appendChild(createRemoveButton(function () {
parentCollection.splice(index, 1);
render();
}));
}

card.appendChild(ruleHeader);
card.appendChild(createOperandField(WPAE_UI.leftOperand, condition.left, function () {
syncTextarea();
}, depth + 1));

const comparisonField = document.createElement('div');
comparisonField.className = 'wpae-field';
const comparisonLabel = document.createElement('label');
comparisonLabel.className = 'wpae-field__label';
comparisonLabel.textContent = WPAE_UI.comparison;
comparisonField.appendChild(comparisonLabel);

const rightOperandWrapper = document.createElement('div');
rightOperandWrapper.className = 'wpae-field';

const comparisonSelect = createSelectFromValues(['==', '!=', '>', '<', '>=', '<=', 'contains', 'empty', 'not_empty'], condition.comparison || '==', function (value) {
condition.comparison = value;
rightOperandWrapper.classList.toggle('is-hidden', value === 'empty' || value === 'not_empty');
syncTextarea();
});

comparisonField.appendChild(comparisonSelect);
card.appendChild(comparisonField);

rightOperandWrapper.appendChild(createOperandField(WPAE_UI.rightOperand, condition.right, function () {
syncTextarea();
}, depth + 1));
rightOperandWrapper.classList.toggle('is-hidden', condition.comparison === 'empty' || condition.comparison === 'not_empty');

card.appendChild(rightOperandWrapper);
container.appendChild(card);
}

function createOperandField(labelText, operand, onChange, depth) {
const wrapper = document.createElement('div');
wrapper.className = 'wpae-field';

const label = document.createElement('label');
label.className = 'wpae-field__label';
label.textContent = labelText;
wrapper.appendChild(label);

const row = document.createElement('div');
row.className = 'wpae-operand-row';

const sourceSelect = createSelectFromValues([
{ value: 'path', label: WPAE_UI.pathSource },
{ value: 'value', label: WPAE_UI.valueSource },
], operand.type || 'value', function (value) {
operand.type = value;
render();
});
row.appendChild(sourceSelect);

if (operand.type === 'path') {
row.appendChild(createStandaloneTextInput(operand.value || '', WPAE_UI.pathPlaceholder, function (value) {
operand.value = value;
onChange();
}));
} else {
row.appendChild(createDataEditor(operand.value, function (value) {
operand.value = value;
onChange();
}, render, {
allowedTypes: WPAE_ALL_DATA_TYPES,
hideLabel: true,
}, depth));
}

wrapper.appendChild(row);
return wrapper;
}

function createTextField(labelText, value, onChange) {
const wrapper = document.createElement('div');
wrapper.className = 'wpae-field';

const label = document.createElement('label');
label.className = 'wpae-field__label';
label.textContent = labelText;
wrapper.appendChild(label);
wrapper.appendChild(createStandaloneTextInput(value, '', onChange));

return wrapper;
}

function createTypeField(node, onChange) {
const wrapper = document.createElement('div');
wrapper.className = 'wpae-field';

const label = document.createElement('label');
label.className = 'wpae-field__label';
label.textContent = WPAE_UI.nodeType;
wrapper.appendChild(label);

const select = document.createElement('select');
select.className = 'regular-text';

schemas.forEach(function (schema) {
const option = document.createElement('option');
option.value = schema.type;
option.textContent = schema.label || schema.type;
option.selected = schema.type === node.type;
select.appendChild(option);
});

select.addEventListener('change', function () {
onChange(select.value);
});

wrapper.appendChild(select);
return wrapper;
}

function createNodeActions(collection, index) {
const actions = document.createElement('div');
actions.className = 'wpae-inline-actions';

actions.appendChild(createSimpleButton(WPAE_UI.moveUp, function () {
if (index < 1) {
return;
}

swapItems(collection, index, index - 1);
render();
}, index < 1));

actions.appendChild(createSimpleButton(WPAE_UI.moveDown, function () {
if (index >= collection.length - 1) {
return;
}

swapItems(collection, index, index + 1);
render();
}, index >= collection.length - 1));

actions.appendChild(createRemoveButton(function () {
collection.splice(index, 1);
render();
}));

return actions;
}

function createAddNodeControls(onAdd) {
const wrapper = document.createElement('div');
wrapper.className = 'wpae-node-list__actions';

const select = document.createElement('select');
select.className = 'regular-text';

schemas.forEach(function (schema) {
const option = document.createElement('option');
option.value = schema.type;
option.textContent = schema.label || schema.type;
select.appendChild(option);
});

wrapper.appendChild(select);
wrapper.appendChild(createSimpleButton(WPAE_UI.addNode, function () {
onAdd(select.value);
}));

return wrapper;
}

function createTextControl(config, key, value, placeholder, isMultiline) {
if (isMultiline) {
return createStandaloneTextarea(value, function (nextValue) {
config[key] = nextValue;
syncTextarea();
});
}

return createStandaloneTextInput(value, placeholder, function (nextValue) {
config[key] = nextValue;
syncTextarea();
});
}

function createSelectControl(config, field, currentValue) {
return createSelectFromValues(field.options || [], currentValue, function (value) {
config[field.name] = value;
syncTextarea();
});
}

render();
}

function createDataEditor(value, onChange, rerender, options, depth) {
const wrapper = document.createElement('div');
wrapper.className = 'wpae-data-editor';
wrapper.setAttribute('data-depth', String(depth || 0));

const allowedTypes = normalizeAllowedDataTypes(options && options.allowedTypes ? options.allowedTypes : WPAE_ALL_DATA_TYPES);
const currentType = getDataValueType(value, allowedTypes[0] || 'string');

if (!(options && options.hideLabel) && allowedTypes.length > 1) {
const typeField = document.createElement('div');
typeField.className = 'wpae-field';

const typeLabel = document.createElement('label');
typeLabel.className = 'wpae-field__label';
typeLabel.textContent = WPAE_UI.valueType;
typeField.appendChild(typeLabel);
typeField.appendChild(createSelectFromValues(allowedTypes.map(function (type) {
return {
value: type,
label: getDataTypeLabel(type),
};
}), currentType, function (nextType) {
onChange(createDefaultValueForType(nextType));
rerender();
}));
wrapper.appendChild(typeField);
}

const content = document.createElement('div');
content.className = 'wpae-data-editor__content';
wrapper.appendChild(content);

renderDataEditorContent(content, currentType, value, onChange, rerender, depth || 0);

return wrapper;
}

function renderDataEditorContent(container, type, value, onChange, rerender, depth) {
switch (type) {
case 'object':
container.appendChild(createObjectEditor(ensureCompositeValue(value, {}), onChange, rerender, depth + 1));
break;
case 'array':
container.appendChild(createArrayEditor(Array.isArray(value) ? value : [], onChange, rerender, depth + 1));
break;
case 'integer':
container.appendChild(createNumberInput(value, true, onChange));
break;
case 'float':
container.appendChild(createNumberInput(value, false, onChange));
break;
case 'boolean':
container.appendChild(createSelectFromValues([
{ value: 'true', label: WPAE_UI.trueLabel },
{ value: 'false', label: WPAE_UI.falseLabel },
], value ? 'true' : 'false', function (nextValue) {
onChange(nextValue === 'true');
}));
break;
case 'null':
container.appendChild(createEditorMessage(WPAE_UI.nullLabel, 'description'));
break;
default:
container.appendChild(createStandaloneTextarea(typeof value === 'string' ? value : '', function (nextValue) {
onChange(nextValue);
}));
break;
}
}

function createObjectEditor(value, onChange, rerender, depth) {
const wrapper = document.createElement('div');
wrapper.className = 'wpae-data-list';

const keys = Object.keys(value);

if (!keys.length) {
wrapper.appendChild(createEditorMessage(WPAE_UI.emptyDataList, 'empty'));
}

keys.forEach(function (key) {
const entry = document.createElement('div');
entry.className = 'wpae-data-entry';
entry.setAttribute('data-depth', String(depth));

const header = document.createElement('div');
header.className = 'wpae-data-entry__header';

const keyField = document.createElement('div');
keyField.className = 'wpae-field';
const keyLabel = document.createElement('label');
keyLabel.className = 'wpae-field__label';
keyLabel.textContent = WPAE_UI.fieldKey;
keyField.appendChild(keyLabel);
const keyInput = createStandaloneTextInput(key, '', function () {});
const commitKeyChange = function () {
const nextKey = ensureUniqueObjectKey(value, keyInput.value || WPAE_UI.defaultFieldKey, key);

if (nextKey === key) {
keyInput.value = key;
return;
}

onChange(renameObjectKey(value, key, nextKey));
rerender();
};
keyInput.addEventListener('change', commitKeyChange);
keyInput.addEventListener('blur', commitKeyChange);
keyField.appendChild(keyInput);
header.appendChild(keyField);

header.appendChild(createSelectFromValues(WPAE_ALL_DATA_TYPES.map(function (type) {
return {
value: type,
label: getDataTypeLabel(type),
};
}), getDataValueType(value[key], 'string'), function (nextType) {
const nextObject = cloneJsonValue(value);
nextObject[key] = createDefaultValueForType(nextType);
onChange(nextObject);
rerender();
}));

header.appendChild(createRemoveButton(function () {
const nextObject = cloneJsonValue(value);
delete nextObject[key];
onChange(nextObject);
rerender();
}));

entry.appendChild(header);

const body = document.createElement('div');
body.className = 'wpae-data-entry__body';
body.appendChild(createDataEditor(value[key], function (nextValue) {
const nextObject = cloneJsonValue(value);
nextObject[key] = nextValue;
onChange(nextObject);
}, rerender, {
allowedTypes: WPAE_ALL_DATA_TYPES,
hideLabel: true,
}, depth + 1));
entry.appendChild(body);

wrapper.appendChild(entry);
});

wrapper.appendChild(createSimpleButton(WPAE_UI.addField, function () {
const nextObject = cloneJsonValue(value);
nextObject[ensureUniqueObjectKey(nextObject, WPAE_UI.defaultFieldKey)] = '';
onChange(nextObject);
rerender();
}));

return wrapper;
}

function createArrayEditor(value, onChange, rerender, depth) {
const wrapper = document.createElement('div');
wrapper.className = 'wpae-data-list';

if (!value.length) {
wrapper.appendChild(createEditorMessage(WPAE_UI.emptyDataList, 'empty'));
}

value.forEach(function (item, index) {
const entry = document.createElement('div');
entry.className = 'wpae-data-entry';
entry.setAttribute('data-depth', String(depth));

const header = document.createElement('div');
header.className = 'wpae-data-entry__header';

const itemTitle = document.createElement('strong');
itemTitle.textContent = WPAE_UI.itemLabel + ' ' + String(index + 1);
header.appendChild(itemTitle);

header.appendChild(createSelectFromValues(WPAE_ALL_DATA_TYPES.map(function (type) {
return {
value: type,
label: getDataTypeLabel(type),
};
}), getDataValueType(item, 'string'), function (nextType) {
const nextArray = value.slice();
nextArray[index] = createDefaultValueForType(nextType);
onChange(nextArray);
rerender();
}));

header.appendChild(createSimpleButton(WPAE_UI.moveUp, function () {
if (index < 1) {
return;
}

const nextArray = value.slice();
swapItems(nextArray, index, index - 1);
onChange(nextArray);
rerender();
}, index < 1));

header.appendChild(createSimpleButton(WPAE_UI.moveDown, function () {
if (index >= value.length - 1) {
return;
}

const nextArray = value.slice();
swapItems(nextArray, index, index + 1);
onChange(nextArray);
rerender();
}, index >= value.length - 1));

header.appendChild(createRemoveButton(function () {
const nextArray = value.slice();
nextArray.splice(index, 1);
onChange(nextArray);
rerender();
}));

entry.appendChild(header);

const body = document.createElement('div');
body.className = 'wpae-data-entry__body';
body.appendChild(createDataEditor(item, function (nextValue) {
const nextArray = value.slice();
nextArray[index] = nextValue;
onChange(nextArray);
}, rerender, {
allowedTypes: WPAE_ALL_DATA_TYPES,
hideLabel: true,
}, depth + 1));
entry.appendChild(body);

wrapper.appendChild(entry);
});

wrapper.appendChild(createSimpleButton(WPAE_UI.addItem, function () {
const nextArray = value.slice();
nextArray.push('');
onChange(nextArray);
rerender();
}));

return wrapper;
}

function createNumberInput(value, integerOnly, onChange) {
const input = document.createElement('input');
input.type = 'number';
input.className = 'regular-text';
input.step = integerOnly ? '1' : 'any';
input.value = typeof value === 'number' ? String(value) : '0';
input.addEventListener('input', function () {
const parsedValue = integerOnly ? parseInt(input.value || '0', 10) : parseFloat(input.value || '0');
onChange(Number.isNaN(parsedValue) ? 0 : parsedValue);
});
return input;
}

function createFieldWithContent(labelText, content) {
const wrapper = document.createElement('div');
wrapper.className = 'wpae-field';

const label = document.createElement('label');
label.className = 'wpae-field__label';
label.textContent = labelText;
wrapper.appendChild(label);
wrapper.appendChild(content);

return wrapper;
}

function createStandaloneTextInput(value, placeholder, onChange) {
const input = document.createElement('input');
input.type = 'text';
input.className = 'regular-text';
input.value = value || '';
input.placeholder = placeholder || '';
input.addEventListener('input', function () {
onChange(input.value);
});
return input;
}

function createStandaloneTextarea(value, onChange) {
const textareaField = document.createElement('textarea');
textareaField.className = 'large-text code';
textareaField.rows = 4;
textareaField.value = value == null ? '' : value;
textareaField.addEventListener('input', function () {
onChange(textareaField.value);
});
return textareaField;
}

function createSelectFromValues(values, currentValue, onChange) {
const select = document.createElement('select');
select.className = 'regular-text';

values.forEach(function (item) {
const option = document.createElement('option');
const value = typeof item === 'string' ? item : item.value;
const label = typeof item === 'string' ? item : item.label || item.value;
option.value = value;
option.textContent = label;
option.selected = value === currentValue;
select.appendChild(option);
});

select.addEventListener('change', function () {
onChange(select.value);
});

return select;
}

function createSimpleButton(label, onClick, disabled) {
const button = document.createElement('button');
button.type = 'button';
button.className = 'button button-secondary';
button.textContent = label;
button.disabled = !!disabled;
button.addEventListener('click', onClick);
return button;
}

function createRemoveButton(onClick) {
return createSimpleButton(WPAE_UI.remove, onClick, false);
}

function createEditorMessage(text, type) {
const message = document.createElement('p');
message.className = 'wpae-editor-message wpae-editor-message--' + type;
message.textContent = text;
return message;
}

function normalizeSchemas(schemas) {
if (!Array.isArray(schemas)) {
return [];
}

return schemas.filter(function (schema) {
return schema && typeof schema.type === 'string' && schema.type !== '';
});
}

function normalizeNodeList(nodes, schemas, schemaMap, state) {
if (!Array.isArray(nodes)) {
return [];
}

return nodes
.filter(function (node) {
return node && typeof node === 'object' && !Array.isArray(node);
})
.map(function (node) {
return normalizeNode(node, schemas, schemaMap, state);
});
}

function normalizeNode(node, schemas, schemaMap, state) {
const normalized = {
id: typeof node.id === 'string' && node.id !== '' ? node.id : createGeneratedNodeId(node.type || 'node', state),
type: typeof node.type === 'string' && node.type !== '' ? node.type : (schemas[0] ? schemas[0].type : 'node'),
config: ensureObject(node.config),
};
const schema = schemaMap[normalized.type] || null;

if (!schema || !Array.isArray(schema.fields)) {
return normalized;
}

schema.fields.forEach(function (field) {
if (field.type === 'nodes') {
normalized.config[field.name] = normalizeNodeList(normalized.config[field.name], schemas, schemaMap, state);
return;
}

if (field.type === 'condition') {
normalized.config[field.name] = normalizeCondition(normalized.config[field.name]);
}
});

return normalized;
}

function createDefaultNode(type, schemas, schemaMap, state) {
const schema = schemaMap[type] || schemas[0] || { type: type, fields: [] };
const config = {};

(schema.fields || []).forEach(function (field) {
switch (field.type) {
case 'select':
config[field.name] = Array.isArray(field.options) && field.options.length ? (typeof field.options[0] === 'string' ? field.options[0] : field.options[0].value) : '';
break;
case 'nodes':
config[field.name] = [];
break;
case 'condition':
config[field.name] = createDefaultConditionGroup();
break;
case 'object':
config[field.name] = {};
break;
case 'mixed':
config[field.name] = '';
break;
default:
config[field.name] = '';
break;
}
});

return {
id: createGeneratedNodeId(type, state),
type: schema.type || type,
config: config,
};
}

function createGeneratedNodeId(type, state) {
state.nodeCounter += 1;
return String(type || 'node')
.replace(/[^a-z0-9_]/gi, '_')
.toLowerCase() + '_' + String(state.nodeCounter);
}

function createDefaultConditionGroup() {
return {
operator: 'AND',
conditions: [createDefaultConditionRule()],
};
}

function createDefaultConditionRule() {
return {
left: { type: 'path', value: '' },
comparison: '==',
right: { type: 'value', value: '' },
};
}

function normalizeCondition(condition) {
if (condition && typeof condition === 'object' && Array.isArray(condition.conditions)) {
return {
operator: condition.operator === 'OR' ? 'OR' : 'AND',
conditions: condition.conditions.map(function (childCondition) {
return normalizeCondition(childCondition);
}),
};
}

if (condition && typeof condition === 'object') {
return {
left: normalizeOperand(condition.left),
comparison: typeof condition.comparison === 'string' && condition.comparison !== '' ? condition.comparison : '==',
right: normalizeOperand(condition.right),
};
}

return createDefaultConditionGroup();
}

function normalizeOperand(operand) {
return {
type: operand && operand.type === 'path' ? 'path' : 'value',
value: operand && Object.prototype.hasOwnProperty.call(operand, 'value') ? operand.value : '',
};
}

function ensureObject(value) {
return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
}

function ensureCompositeValue(value, fallback) {
if (Array.isArray(value)) {
return value;
}

if (value && typeof value === 'object') {
return value;
}

return fallback;
}

function parseNodeList(value, fallback) {
const parsed = parseJsonInput(value, false);
return parsed.valid && Array.isArray(parsed.value) ? parsed.value : fallback;
}

function parseJsonInput(value, defaultToObject) {
const trimmed = String(value || '').trim();

if (trimmed === '') {
return {
valid: true,
value: defaultToObject ? {} : '',
};
}

try {
const parsed = JSON.parse(trimmed);
return {
valid: true,
value: parsed,
};
} catch (error) {
return {
valid: false,
value: defaultToObject ? {} : '',
};
}
}

function getFieldLabel(field) {
if (field && typeof field.label === 'string' && field.label !== '') {
return field.label;
}

if (field && field.name && Object.prototype.hasOwnProperty.call(WPAE_FIELD_LABELS, field.name)) {
return WPAE_FIELD_LABELS[field.name];
}

return formatFieldLabel(field ? field.name : '');
}

function formatFieldLabel(name) {
return String(name || '')
.replace(/_/g, ' ')
.replace(/^./, function (character) {
return character.toUpperCase();
});
}

function getDataTypeLabel(type) {
switch (type) {
case 'object':
return WPAE_UI.objectType;
case 'array':
return WPAE_UI.arrayType;
case 'integer':
return WPAE_UI.integerType;
case 'float':
return WPAE_UI.floatType;
case 'boolean':
return WPAE_UI.booleanType;
case 'null':
return WPAE_UI.nullType;
default:
return WPAE_UI.stringType;
}
}

function getDataValueType(value, fallbackType) {
if (Array.isArray(value)) {
return 'array';
}

if (value === null) {
return 'null';
}

if (typeof value === 'boolean') {
return 'boolean';
}

if (typeof value === 'number') {
return Number.isInteger(value) ? 'integer' : 'float';
}

if (value && typeof value === 'object') {
return 'object';
}

if (typeof value === 'string') {
return 'string';
}

return fallbackType || 'string';
}

function createDefaultValueForType(type) {
switch (type) {
case 'object':
return {};
case 'array':
return [];
case 'integer':
return 0;
case 'float':
return 0;
case 'boolean':
return false;
case 'null':
return null;
default:
return '';
}
}

function normalizeAllowedDataTypes(types) {
return Array.isArray(types) && types.length ? types : WPAE_ALL_DATA_TYPES;
}

function isCompositeValue(value) {
return Array.isArray(value) || (value && typeof value === 'object');
}

function ensureUniqueObjectKey(objectValue, desiredKey, currentKey) {
const baseKey = String(desiredKey || WPAE_UI.defaultFieldKey)
.trim()
.replace(/\s+/g, '_') || WPAE_UI.defaultFieldKey;
let nextKey = baseKey;
let index = 1;

while (Object.prototype.hasOwnProperty.call(objectValue, nextKey) && nextKey !== currentKey) {
nextKey = baseKey + '_' + String(index);
index += 1;
}

return nextKey;
}

function renameObjectKey(objectValue, sourceKey, targetKey) {
const nextObject = {};

Object.keys(objectValue).forEach(function (key) {
if (key === sourceKey) {
nextObject[targetKey] = objectValue[key];
return;
}

nextObject[key] = objectValue[key];
});

return nextObject;
}

function cloneJsonValue(value) {
return JSON.parse(JSON.stringify(value));
}

function isConditionGroup(condition) {
return condition && typeof condition === 'object' && Array.isArray(condition.conditions);
}

function swapItems(collection, sourceIndex, targetIndex) {
const source = collection[sourceIndex];
collection[sourceIndex] = collection[targetIndex];
collection[targetIndex] = source;
}
