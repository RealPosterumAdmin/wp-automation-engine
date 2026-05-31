document.addEventListener('DOMContentLoaded', function () {
	initTriggerFields();
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

	const reloadButton = document.getElementById('wpae-node-editor-reload');
	const schemas = normalizeSchemas((window.wpaeAdminConfig && window.wpaeAdminConfig.schemas) || []);
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
		mount.appendChild(createEditorMessage('Visual node editor', 'description'));
		renderNodeList(mount, state.nodes, 0);
		syncTextarea();
	}

	function renderNodeList(container, nodes, depth) {
		const list = document.createElement('div');
		list.className = 'wpae-node-list';

		if (!nodes.length) {
			list.appendChild(createEditorMessage('No nodes configured yet.', 'empty'));
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
		titleText.textContent = schema ? schema.label || schema.type : 'Unknown node';
		title.appendChild(titleText);

		const typeCode = document.createElement('code');
		typeCode.textContent = node.type || '';
		title.appendChild(typeCode);

		header.appendChild(title);
		header.appendChild(createNodeActions(collection, index));
		card.appendChild(header);

		const body = document.createElement('div');
		body.className = 'wpae-node-card__body';

		body.appendChild(createTextField('Node ID', node.id || '', function (value) {
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
			body.appendChild(createJsonTextareaField('Config JSON', node.config || {}, function (value) {
				node.config = value;
				syncTextarea();
			}));

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
		const wrapper = document.createElement('div');
		wrapper.className = 'wpae-field';

		const label = document.createElement('label');
		label.className = 'wpae-field__label';
		label.textContent = formatFieldLabel(field.name);
		wrapper.appendChild(label);

		switch (field.type) {
			case 'text':
			case 'path':
				wrapper.appendChild(createTextControl(config, field.name, config[field.name] || '', field.type === 'path' ? 'variables.orders' : '', false));
				break;
			case 'select':
				wrapper.appendChild(createSelectControl(config, field, config[field.name] || ''));
				break;
			case 'mixed':
				wrapper.appendChild(createMixedTextareaControl(config, field.name, config[field.name]));
				break;
			case 'object':
				wrapper.appendChild(createJsonTextareaControl(config, field.name, config[field.name], true));
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
				wrapper.appendChild(createJsonTextareaControl(config, field.name, config[field.name], false));
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
			title.textContent = 'Condition Group';
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
			actions.appendChild(createSimpleButton('Add Rule', function () {
				condition.conditions.push(createDefaultConditionRule());
				render();
			}));
			actions.appendChild(createSimpleButton('Add Group', function () {
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
		title.textContent = 'Condition Rule';
		ruleHeader.appendChild(title);

		if (parentCollection) {
			ruleHeader.appendChild(createRemoveButton(function () {
				parentCollection.splice(index, 1);
				render();
			}));
		}

		card.appendChild(ruleHeader);
		card.appendChild(createOperandField('Left Operand', condition.left, function () {
			syncTextarea();
		}));

		const comparisonField = document.createElement('div');
		comparisonField.className = 'wpae-field';
		const comparisonLabel = document.createElement('label');
		comparisonLabel.className = 'wpae-field__label';
		comparisonLabel.textContent = 'Comparison';
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

		rightOperandWrapper.appendChild(createOperandField('Right Operand', condition.right, function () {
			syncTextarea();
		}));
		rightOperandWrapper.classList.toggle('is-hidden', condition.comparison === 'empty' || condition.comparison === 'not_empty');

		card.appendChild(rightOperandWrapper);
		container.appendChild(card);
	}

	function createOperandField(labelText, operand, onChange) {
		const wrapper = document.createElement('div');
		wrapper.className = 'wpae-field';

		const label = document.createElement('label');
		label.className = 'wpae-field__label';
		label.textContent = labelText;
		wrapper.appendChild(label);

		const row = document.createElement('div');
		row.className = 'wpae-operand-row';

		const sourceSelect = createSelectFromValues(['path', 'value'], operand.type || 'value', function (value) {
			operand.type = value;
			render();
		});
		row.appendChild(sourceSelect);

		if (operand.type === 'path') {
			row.appendChild(createStandaloneTextInput(operand.value || '', 'variables.message', function (value) {
				operand.value = value;
				onChange();
			}));
		} else {
			row.appendChild(createStandaloneTextarea(formatMixedValue(operand.value), function (value) {
				operand.value = parseMixedValue(value);
				onChange();
			}));
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
		label.textContent = 'Node Type';
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

		actions.appendChild(createSimpleButton('Up', function () {
			if (index < 1) {
				return;
			}

			swapItems(collection, index, index - 1);
			render();
		}, index < 1));

		actions.appendChild(createSimpleButton('Down', function () {
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
		wrapper.appendChild(createSimpleButton('Add Node', function () {
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

	function createMixedTextareaControl(config, key, value) {
		return createStandaloneTextarea(formatMixedValue(value), function (nextValue) {
			config[key] = parseMixedValue(nextValue);
			syncTextarea();
		});
	}

	function createJsonTextareaControl(config, key, value, defaultToObject) {
		return createJsonTextareaField('', value, function (nextValue) {
			config[key] = nextValue;
			syncTextarea();
		}, defaultToObject);
	}

	function createJsonTextareaField(labelText, value, onChange, defaultToObject) {
		const wrapper = document.createElement('div');
		wrapper.className = 'wpae-json-field';

		if (labelText) {
			const label = document.createElement('label');
			label.className = 'wpae-field__label';
			label.textContent = labelText;
			wrapper.appendChild(label);
		}

		const textareaField = document.createElement('textarea');
		textareaField.className = 'large-text code';
		textareaField.rows = 6;
		textareaField.value = formatJsonValue(value, defaultToObject);

		const error = createEditorMessage('Invalid JSON', 'error');
		error.classList.add('is-hidden');

		textareaField.addEventListener('input', function () {
			const parsed = parseJsonInput(textareaField.value, defaultToObject);

			if (parsed.valid) {
				textareaField.classList.remove('wpae-input-error');
				error.classList.add('is-hidden');
				onChange(parsed.value);
				return;
			}

			textareaField.classList.add('wpae-input-error');
			error.classList.remove('is-hidden');
		});

		wrapper.appendChild(textareaField);
		wrapper.appendChild(error);
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
		textareaField.value = value || '';
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
		return createSimpleButton('Remove', onClick, false);
	}

	function createEditorMessage(text, type) {
		const message = document.createElement('p');
		message.className = 'wpae-editor-message wpae-editor-message--' + type;
		message.textContent = text;
		return message;
	}

	function parseNodesFromTextarea() {
		const parsed = parseJsonInput(textarea.value, false);

		if (!parsed.valid || !Array.isArray(parsed.value)) {
			window.alert('Nodes JSON must be a valid array.');
			return null;
		}

		return normalizeNodeList(parsed.value, schemas, schemaMap, state);
	}

	if (reloadButton) {
		reloadButton.addEventListener('click', function () {
			const parsedNodes = parseNodesFromTextarea();

			if (!parsedNodes) {
				return;
			}

			state.nodes = parsedNodes;
			render();
		});
	}

	render();
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

function parseMixedValue(value) {
	const parsed = parseJsonInput(value, false);
	return parsed.valid ? parsed.value : value;
}

function formatMixedValue(value) {
	if (typeof value === 'string') {
		return value;
	}

	if (typeof value === 'undefined') {
		return '';
	}

	return JSON.stringify(value, null, 2);
}

function formatJsonValue(value, defaultToObject) {
	if (typeof value === 'undefined' || value === '') {
		return defaultToObject ? '{\n\n}' : '';
	}

	return JSON.stringify(value, null, 2);
}

function formatFieldLabel(name) {
	return String(name || '')
		.replace(/_/g, ' ')
		.replace(/\b\w/g, function (character) {
			return character.toUpperCase();
		});
}

function isConditionGroup(condition) {
	return condition && typeof condition === 'object' && Array.isArray(condition.conditions);
}

function swapItems(collection, sourceIndex, targetIndex) {
	const source = collection[sourceIndex];
	collection[sourceIndex] = collection[targetIndex];
	collection[targetIndex] = source;
}
