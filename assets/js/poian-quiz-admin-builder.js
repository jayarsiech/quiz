/**
 * Poian Quiz — Admin Builder v2 (IIFE)
 * ساختار جدید: fields به جای pages، layout دو ستونی
 */
(function () {
	'use strict';

	/* ---------------- ابزارهای کمکی ---------------- */
	function el(tag, cls, html) {
		var n = document.createElement(tag);
		if (cls) { n.className = cls; }
		if (html != null) { n.innerHTML = html; }
		return n;
	}
	function esc(s) {
		var d = document.createElement('div');
		d.textContent = (s == null ? '' : String(s));
		return d.innerHTML;
	}
	function uid(p) { return p + Math.random().toString(36).slice(2, 8); }
	function input(val, ph) {
		var i = el('input', 'pq-in');
		i.type = 'text';
		i.value = (val == null ? '' : val);
		if (ph) { i.placeholder = ph; }
		return i;
	}
	function textarea(val) {
		var t = el('textarea', 'pq-ta');
		t.value = (val == null ? '' : val);
		return t;
	}
	function btn(label, cls) { return el('button', 'button ' + (cls || ''), esc(label)); }
	function wrapLbl(label, node) {
		var w = el('div', 'pq-field');
		w.appendChild(el('label', 'pq-lbl', esc(label)));
		w.appendChild(node);
		return w;
	}

	/* آیکون‌های SVG */
	var ICONS = {
		up: '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M7.41 15.41 12 10.83l4.59 4.58L18 14l-6-6-6 6z"/></svg>',
		down: '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z"/></svg>',
		del: '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>',
		drag: '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M9 5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm0 7a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm0 7a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm9-14a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm0 7a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm0 7a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/></svg>'
	};
	function iconBtn(icon, title, cls) {
		var b = el('button', 'pq-icon-btn ' + (cls || ''));
		b.type = 'button';
		b.innerHTML = ICONS[icon] || '';
		b.title = title || '';
		b.setAttribute('aria-label', title || '');
		return b;
	}

	/* ---------------- State ---------------- */
	var root = document.getElementById('pq-builder');
	var toolboxRoot = document.getElementById('pq-builder-toolbox');
	if (!root) { return; }

	var S = {
		schema: JSON.parse(root.getAttribute('data-schema') || '{"title":"","fields":[]}'),
		actions: JSON.parse(root.getAttribute('data-actions') || '{"meta":[],"sms":0,"redirect":""}'),
		settings: JSON.parse(root.getAttribute('data-settings') || '{}'),
		engine: root.getAttribute('data-engine') || 'none',
		engines: JSON.parse(root.getAttribute('data-engines') || '[]'),
		config: JSON.parse(root.getAttribute('data-engine-config') || '{}'),
		openFields: {},
		scrollTargetId: null,
		scrollTargetType: null
	};

	// Backward compatibility: اگر ساختار قدیم pages بود، تبدیل به fields
	if (!S.schema.fields && S.schema.pages) {
		S.schema.fields = [];
		S.schema.pages.forEach(function (page) {
			if (page.type === 'content') {
				S.schema.fields.push({
					id: page.id || uid('pb'),
					type: 'page_break',
					heading: page.heading || '',
					description: page.description || '',
					next_label: '',
					prev_label: ''
				});
			}
			if (page.fields) {
				page.fields.forEach(function (f) { S.schema.fields.push(f); });
			}
		});
		delete S.schema.pages;
	}
	if (!S.schema.fields) { S.schema.fields = []; }
	if (!S.actions.meta) { S.actions.meta = []; }

	/* ---------------- تعاریف فیلدها ---------------- */
	var FIELD_DEFS = {
		text:        { icon: '✏️', label: 'متن تک‌خطی', numbered: false },
		textarea:    { icon: '📝', label: 'متن پاراگرافی', numbered: false },
		radio:       { icon: '🔘', label: 'رادیو', numbered: true },
		checkbox:    { icon: '☑️', label: 'چک‌باکس', numbered: true },
		select:      { icon: '📋', label: 'انتخابی (لیست)', numbered: true },
		rank:        { icon: '↕️', label: 'درگ‌دراپ (رتبه)', numbered: true },
		description: { icon: '💬', label: 'صفحه توضیحات', numbered: false },
		page_break:  { icon: '📄', label: 'جداکننده صفحه', numbered: false }
	};

	function hasWeights() {
		for (var i = 0; i < S.engines.length; i++) {
			if (S.engines[i].id === S.engine) { return !!S.engines[i].weights; }
		}
		return false;
	}

	function allFields() {
		return S.schema.fields.filter(function (f) {
			return f.type !== 'page_break' && f.type !== 'description';
		});
	}

	function move(arr, i, dir) {
		var j = i + dir;
		if (j < 0 || j >= arr.length) { return; }
		var t = arr[i]; arr[i] = arr[j]; arr[j] = t;
	}

	/* ---------------- رندر Toolbox ---------------- */
	function renderToolbox() {
		if (!toolboxRoot) { return; }
		toolboxRoot.innerHTML = '';

		/* آکاردیون ۱: سوال شماره‌دار */
		var acc1 = el('div', 'pq-accordion pq-open');
		var h1 = el('div', 'pq-accordion-header', '➕ سوال شماره‌دار');
		h1.addEventListener('click', function () { acc1.classList.toggle('pq-open'); });
		var b1 = el('div', 'pq-accordion-body');

		var numberedTypes = ['radio', 'checkbox', 'select', 'text', 'textarea', 'rank'];
		numberedTypes.forEach(function (type) {
			var def = FIELD_DEFS[type];
			var item = el('div', 'pq-toolbox-item pq-numbered');
			item.setAttribute('data-type', type);
			item.setAttribute('data-numbered', '1');
			item.innerHTML = '<span class="pq-item-icon">' + def.icon + '</span><span class="pq-item-label">' + def.label + '</span>';
			item.addEventListener('click', function () { addField(type, true); });
			b1.appendChild(item);
		});
		acc1.appendChild(h1);
		acc1.appendChild(b1);
		toolboxRoot.appendChild(acc1);

		/* آکاردیون ۲: فیلدهای استاندارد */
		var acc2 = el('div', 'pq-accordion pq-open');
		var h2 = el('div', 'pq-accordion-header', '🧩 فیلدهای استاندارد');
		h2.addEventListener('click', function () { acc2.classList.toggle('pq-open'); });
		var b2 = el('div', 'pq-accordion-body');

		var stdTypes = ['text', 'textarea', 'radio', 'checkbox', 'select', 'rank', 'description', 'page_break'];
		stdTypes.forEach(function (type) {
			var def = FIELD_DEFS[type];
			var item = el('div', 'pq-toolbox-item');
			item.setAttribute('data-type', type);
			item.innerHTML = '<span class="pq-item-icon">' + def.icon + '</span><span class="pq-item-label">' + def.label + '</span>';
			item.addEventListener('click', function () { addField(type, false); });
			b2.appendChild(item);
		});
		acc2.appendChild(h2);
		acc2.appendChild(b2);
		toolboxRoot.appendChild(acc2);

		/* آکاردیون ۳: موتور آزمون */
		var acc3 = el('div', 'pq-accordion');
		var h3 = el('div', 'pq-accordion-header', '🎯 موتور آزمون');
		h3.addEventListener('click', function () { acc3.classList.toggle('pq-open'); });
		var b3 = el('div', 'pq-accordion-body');

		S.engines.forEach(function (eng) {
			var item = el('div', 'pq-toolbox-item');
			item.innerHTML = '<input type="radio" name="pq_engine_radio" value="' + eng.id + '"' + (S.engine === eng.id ? ' checked' : '') + ' /> <span class="pq-item-label">' + esc(eng.title) + '</span>';
			item.querySelector('input').addEventListener('change', function () {
				S.engine = eng.id;
				renderCanvas();
			});
			b3.appendChild(item);
		});
		acc3.appendChild(h3);
		acc3.appendChild(b3);
		toolboxRoot.appendChild(acc3);

		/* فعال‌سازی drag برای آیتم‌های Toolbox */
		initToolboxDrag();
	}
	/* ---------------- Drag از Toolbox به Canvas ---------------- */
	function initToolboxDrag() {
		if (!toolboxRoot) { return; }

		toolboxRoot.querySelectorAll('.pq-toolbox-item').forEach(function (item) {
			item.setAttribute('draggable', 'true');

			item.addEventListener('dragstart', function (e) {
				e.dataTransfer.setData('text/plain', JSON.stringify({
					type: item.getAttribute('data-type'),
					numbered: item.getAttribute('data-numbered') === '1'
				}));
				e.dataTransfer.effectAllowed = 'copy';
				item.classList.add('pq-dragging-from-toolbox');
			});

			item.addEventListener('dragend', function () {
				item.classList.remove('pq-dragging-from-toolbox');
			});
		});

		/* Canvas به عنوان drop target */
		var canvas = document.getElementById('pq-canvas');
		if (!canvas) { return; }

		canvas.addEventListener('dragover', function (e) {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'copy';
			canvas.classList.add('pq-drag-over');
		});

		canvas.addEventListener('dragleave', function () {
			canvas.classList.remove('pq-drag-over');
		});

		canvas.addEventListener('drop', function (e) {
			e.preventDefault();
			canvas.classList.remove('pq-drag-over');

			try {
				var data = JSON.parse(e.dataTransfer.getData('text/plain'));
				if (data && data.type) {
					addField(data.type, data.numbered);
				}
			} catch (err) {
				// ignore invalid data
			}
		});
	}
		/* ---------------- SortableJS برای جابجایی فیلدها در Canvas ---------------- */
	var pqSortableInstance = null;

	function initSortable() {
		if (typeof Sortable === 'undefined') { return; }

		var fieldsList = root.querySelector('.pq-fields-list');
		if (!fieldsList) { return; }

		// destroy instance قبلی
		if (pqSortableInstance) {
			pqSortableInstance.destroy();
			pqSortableInstance = null;
		}

		pqSortableInstance = new Sortable(fieldsList, {
			animation: 200,
			handle: '.pq-field-head',
			ghostClass: 'pq-sortable-ghost',
			chosenClass: 'pq-sortable-chosen',
			dragClass: 'pq-sortable-drag',
			onEnd: function (evt) {
				syncFieldsOrder();
				renumberQuestions();
			}
		});
	}

	function syncFieldsOrder() {
		var fieldsList = root.querySelector('.pq-fields-list');
		if (!fieldsList) { return; }

		var newOrder = [];
		fieldsList.querySelectorAll('.pq-fieldcard, .pq-page-break-card').forEach(function (el) {
			var fid = el.querySelector('.pq-field-id');
			if (!fid) { return; }
			var id = fid.textContent.replace('#', '');
			for (var i = 0; i < S.schema.fields.length; i++) {
				if (S.schema.fields[i].id === id) {
					newOrder.push(S.schema.fields[i]);
					break;
				}
			}
		});

		// فقط اگر همه فیلدها پیدا شدند، order را آپدیت کن
		if (newOrder.length === S.schema.fields.length) {
			S.schema.fields = newOrder;
		}
	}
	/* ---------------- افزودن فیلد ---------------- */
	function addField(type, numbered) {
		var newId = uid('f');
		var needsOpts = (type === 'radio' || type === 'checkbox' || type === 'select' || type === 'rank');
		var field = {
			id: newId,
			type: type,
			title: '',
			description: '',
			required: 0,
			dim: '',
			options: needsOpts ? [
				{ key: 'a', label: '', weight: 4 },
				{ key: 'b', label: '', weight: 3 },
				{ key: 'c', label: '', weight: 2 },
				{ key: 'd', label: '', weight: 1 }
			] : [],
			conditions: [],
			condition_logic: 'all',
			condition_action: 'show'
		};

		if (type === 'page_break') {
			field.heading = '';
			field.next_label = '';
			field.prev_label = '';
		}

		if (numbered) {
			field.question_number = getNextQuestionNumber();
		}

		S.openFields[newId] = true;
		S.scrollTargetId = newId;
		S.scrollTargetType = 'field';
		S.schema.fields.push(field);
		renderCanvas();
	}

	function getNextQuestionNumber() {
		var max = 0;
		S.schema.fields.forEach(function (f) {
			if (f.question_number && f.question_number > max) {
				max = f.question_number;
			}
		});
		return max + 1;
	}

	function renumberQuestions() {
		var num = 1;
		S.schema.fields.forEach(function (f) {
			if (f.question_number) {
				f.question_number = num;
				num++;
			}
		});
	}

	/* ---------------- رندر Canvas ---------------- */
	function renderCanvas() {
		var scrollPos = window.scrollY;

		// 🆕 قبل از re-render، محتوای نهایی را از TinyMCE بگیر
		if (window.tinyMCE && typeof window.tinyMCE.get === 'function') {
			document.querySelectorAll('.pq-person-editor').forEach(function (ta) {
				var editor = window.tinyMCE.get(ta.id);
				if (editor && typeof editor.getContent === 'function') {
					var key = ta.id.replace('pq-person-editor-', '');
					if (S.config.personalities && S.config.personalities[key]) {
						S.config.personalities[key].content = editor.getContent();
					}
				}
			});
		}

		// 🆕 قبل از re-render، destroy همه TinyMCE instances
		if (window.tinyMCE && typeof window.tinyMCE.remove === 'function') {
			try { window.tinyMCE.remove(); } catch (e) {}
		}
		// ذخیره state آکاردیون‌ها
		root.querySelectorAll('.pq-fieldcard').forEach(function (fc) {
			var fid = fc.querySelector('.pq-field-id');
			if (fid) {
				var id = fid.textContent.replace('#', '');
				S.openFields[id] = !fc.classList.contains('pq-collapsed');
			}
		});

		root.innerHTML = '';

		/* عنوان فرم */
		var titleCard = el('div', 'pq-card');
		titleCard.appendChild(el('h3', 'pq-card-title', 'عنوان فرم'));
		var titleInput = input(S.schema.title, 'عنوان فرم را وارد کنید');
		titleInput.addEventListener('input', function () { S.schema.title = titleInput.value; });
		titleCard.appendChild(titleInput);
		root.appendChild(titleCard);

		/* فیلدها */
		if (S.schema.fields.length === 0) {
			var empty = el('div', 'pq-canvas-empty');
			empty.innerHTML = '<div class="pq-empty-icon">📋</div><p>فرم خالی است. از منوی سمت چپ فیلد اضافه کنید.</p>';
			root.appendChild(empty);
		} else {
			var fieldsWrap = el('div', 'pq-fields-list');
			S.schema.fields.forEach(function (f, idx) {
				if (f.type === 'page_break') {
					fieldsWrap.appendChild(renderPageBreak(f, idx));
				} else {
					fieldsWrap.appendChild(renderField(f, idx));
				}
			});
			root.appendChild(fieldsWrap);
		}

		/* اکشن‌ها */
		renderActions();

		/* برگرداندن state آکاردیون‌ها */
		root.querySelectorAll('.pq-fieldcard').forEach(function (fc) {
			var fid = fc.querySelector('.pq-field-id');
			if (fid) {
				var id = fid.textContent.replace('#', '');
				if (S.openFields[id]) {
					fc.classList.remove('pq-collapsed');
				}
			}
		});

		/* اسکرول به فیلد جدید */
		if (S.scrollTargetId) {
			var target = null;
			root.querySelectorAll('.pq-field-id').forEach(function (fid) {
				if (fid.textContent === '#' + S.scrollTargetId) {
					target = fid.closest('.pq-fieldcard');
				}
			});
			if (target) {
				setTimeout(function () {
					target.scrollIntoView({ behavior: 'smooth', block: 'center' });
					target.classList.add('pq-highlight-new');
					setTimeout(function () { target.classList.remove('pq-highlight-new'); }, 2000);
				}, 50);
			}
			S.scrollTargetId = null;
			S.scrollTargetType = null;
		} else {
			window.scrollTo(0, scrollPos);
		}

		/* بخش مدیریت مولفه‌ها (فقط برای موتورهای وزنی) */
		if (hasWeights() && S.engine === 'mehdyar') {
			renderDimensionsManager();
			renderPersonalitiesManager();
		}

		/* فعال‌سازی SortableJS برای جابجایی فیلدها */
		initSortable();
	}

	/* ---------------- رندر Page Break ---------------- */
	function renderPageBreak(f, idx) {
		var fc = el('div', 'pq-fieldcard pq-collapsed pq-page-break-card');

		/* هدر */
		var head = el('div', 'pq-field-head');
		head.appendChild(el('span', 'pq-field-badge', '📄 جداکننده صفحه'));
		head.appendChild(el('span', 'pq-field-title', esc(f.heading || 'جداکننده صفحه')));
		head.appendChild(el('span', 'pq-field-id', '#' + f.id));

		var headBtns = el('div', 'pq-field-head-btns');
		var dragHandle = el('span', 'pq-drag-handle');
		dragHandle.innerHTML = ICONS.drag;
		dragHandle.title = 'بکشید و جابجا کنید';
		headBtns.appendChild(dragHandle);

		var up = iconBtn('up', 'انتقال به بالا');
		var dn = iconBtn('down', 'انتقال به پایین');
		var del = iconBtn('del', 'حذف', 'pq-danger');
		up.addEventListener('click', function (e) { e.stopPropagation(); move(S.schema.fields, idx, -1); renderCanvas(); });
		dn.addEventListener('click', function (e) { e.stopPropagation(); move(S.schema.fields, idx, 1); renderCanvas(); });
		del.addEventListener('click', function (e) {
			e.stopPropagation();
			if (window.confirm('این جداکننده صفحه حذف شود؟')) {
				S.schema.fields.splice(idx, 1);
				renderCanvas();
			}
		});
		headBtns.appendChild(up);
		headBtns.appendChild(dn);
		headBtns.appendChild(del);
		head.appendChild(headBtns);

		/* کلیک روی هدر = toggle باز/بسته */
		head.addEventListener('click', function () {
			fc.classList.toggle('pq-collapsed');
		});
		fc.appendChild(head);

		/* بدنه */
		var body = el('div', 'pq-field-body');

		/* اگر اولین Page Break است، تنظیمات نوار پیشرفت را نشان بده */
		if (isFirstPageBreak(f)) {
			var progressWrap = el('div', 'pq-pb-progress-settings');
			progressWrap.appendChild(el('h4', 'pq-pb-section-title', 'تنظیمات نمایش'));

			var progressRow = el('div', 'pq-row');
			var progressLabel = el('label', 'pq-lbl', 'نمایش نوار پیشرفت:');
			var progressSelect = el('select', 'pq-in');
			progressSelect.innerHTML = '<option value="inherit">ارث از تنظیمات کلی</option><option value="1">نمایش</option><option value="0">عدم نمایش</option>';
			progressSelect.value = f.show_progress || 'inherit';
			progressSelect.addEventListener('change', function () {
				f.show_progress = progressSelect.value;
			});
			progressRow.appendChild(progressLabel);
			progressRow.appendChild(progressSelect);
			progressWrap.appendChild(progressRow);
			body.appendChild(progressWrap);
		}

		var hInput = input(f.heading, 'عنوان صفحه (اختیاری)');
		hInput.addEventListener('input', function () { f.heading = hInput.value; });
		body.appendChild(wrapLbl('عنوان صفحه', hInput));

		var descInput = textarea(f.description);
		descInput.addEventListener('input', function () { f.description = descInput.value; });
		body.appendChild(wrapLbl('توضیحات صفحه', descInput));

		var nextInput = input(f.next_label, 'متن دکمه بعدی (پیش‌فرض: بعدی)');
		nextInput.addEventListener('input', function () { f.next_label = nextInput.value; });
		body.appendChild(wrapLbl('متن دکمه بعدی', nextInput));

		var prevInput = input(f.prev_label, 'متن دکمه قبلی (پیش‌فرض: قبلی)');
		prevInput.addEventListener('input', function () { f.prev_label = prevInput.value; });
		body.appendChild(wrapLbl('متن دکمه قبلی', prevInput));

		fc.appendChild(body);
		return fc;
	}

	/**
	 * بررسی اینکه آیا این اولین Page Break است یا نه.
	 */
	function isFirstPageBreak(f) {
		for (var i = 0; i < S.schema.fields.length; i++) {
			if (S.schema.fields[i].type === 'page_break') {
				return S.schema.fields[i].id === f.id;
			}
		}
		return false;
	}
	/* ---------------- رندر فیلد ---------------- */
	function renderField(f, idx) {
		var fc = el('div', 'pq-fieldcard pq-collapsed');
		var def = FIELD_DEFS[f.type] || { icon: '❓', label: f.type };

		/* هدر */
		var head = el('div', 'pq-field-head');
		head.appendChild(el('span', 'pq-field-badge', def.icon + ' ' + def.label));
		head.appendChild(el('span', 'pq-field-title', esc(f.title || 'بدون عنوان')));

		if (f.question_number) {
			head.appendChild(el('span', 'pq-field-number', String(f.question_number)));
		}

		head.appendChild(el('span', 'pq-field-id', '#' + f.id));

		var headBtns = el('div', 'pq-field-head-btns');
		var dragHandle = el('span', 'pq-drag-handle');
		dragHandle.innerHTML = ICONS.drag;
		dragHandle.title = 'بکشید و جابجا کنید';
		headBtns.appendChild(dragHandle);

		var up = iconBtn('up', 'انتقال به بالا');
		var dn = iconBtn('down', 'انتقال به پایین');
		var del = iconBtn('del', 'حذف فیلد', 'pq-danger');
		up.addEventListener('click', function (e) { e.stopPropagation(); move(S.schema.fields, idx, -1); renumberQuestions(); renderCanvas(); });
		dn.addEventListener('click', function (e) { e.stopPropagation(); move(S.schema.fields, idx, 1); renumberQuestions(); renderCanvas(); });
		del.addEventListener('click', function (e) {
			e.stopPropagation();
			if (window.confirm('این فیلد حذف شود؟')) {
				S.schema.fields.splice(idx, 1);
				renumberQuestions();
				renderCanvas();
			}
		});
		headBtns.appendChild(up);
		headBtns.appendChild(dn);
		headBtns.appendChild(del);
		head.appendChild(headBtns);

		head.addEventListener('click', function () {
			fc.classList.toggle('pq-collapsed');
		});
		fc.appendChild(head);

		/* بدنه */
		var body = el('div', 'pq-field-body');

		/* عنوان */
		var t = input(f.title, 'عنوان سوال/فیلد');
		t.addEventListener('input', function () { f.title = t.value; });
		body.appendChild(wrapLbl('عنوان', t));

		/* توضیحات */
		if (f.type !== 'description') {
			var d = textarea(f.description);
			d.addEventListener('input', function () { f.description = d.value; });
			body.appendChild(wrapLbl('توضیح فیلد', d));
		} else {
			var d2 = textarea(f.description);
			d2.addEventListener('input', function () { f.description = d2.value; });
			body.appendChild(wrapLbl('متن توضیحات', d2));
		}

		/* الزامی و بُعد */
		if (f.type !== 'description' && f.type !== 'page_break') {
			var row = el('div', 'pq-grid');
			var req = el('input');
			req.type = 'checkbox';
			req.checked = !!f.required;
			req.addEventListener('change', function () { f.required = req.checked ? 1 : 0; });
			row.appendChild(wrapLbl('الزامی', req));

			if (hasWeights()) {
				var dim = input(f.dim, 'کلید بُعد (مثل fiqh)');
				dim.addEventListener('input', function () { f.dim = dim.value.replace(/[^a-z0-9_]/gi, ''); });
				row.appendChild(wrapLbl('بُعد/مولفه', dim));
			}
			body.appendChild(row);
		}

		/* گزینه‌ها */
		if (f.type === 'radio' || f.type === 'checkbox' || f.type === 'rank') {
			if (!f.options) { f.options = []; }
			var ow = el('div', 'pq-opts');
			f.options.forEach(function (o, oi) {
				var orow = el('div', 'pq-row');
				var ol = input(o.label, 'متن گزینه');
				ol.addEventListener('input', function () { o.label = ol.value; });
				orow.appendChild(ol);
				if (hasWeights()) {
					var w = el('input', 'pq-in pq-w');
					w.type = 'number';
					w.value = o.weight;
					w.addEventListener('input', function () { o.weight = parseFloat(w.value) || 0; });
					orow.appendChild(w);
				}
				var odel = iconBtn('del', 'حذف گزینه', 'pq-danger');
				odel.addEventListener('click', function () { f.options.splice(oi, 1); renderCanvas(); });
				orow.appendChild(odel);
				ow.appendChild(orow);
			});
			var oadd = btn('+ گزینه');
			oadd.addEventListener('click', function () {
				f.options.push({ key: uid('o'), label: '', weight: 0 });
				renderCanvas();
			});
			ow.appendChild(oadd);
			body.appendChild(wrapLbl('گزینه‌ها', ow));
		}

		fc.appendChild(body);
		return fc;
	}

	/* ---------------- اکشن‌ها ---------------- */
	function renderActions() {
		var c = el('div', 'pq-card');
		c.appendChild(el('h3', 'pq-card-title', 'اکشن‌ها (متا / پیامک / ریدایرکت)'));

		var mw = el('div', 'pq-opts');
		S.actions.meta.forEach(function (m, mi) {
			var row = el('div', 'pq-row');
			var k = input(m.key, 'کلید متا');
			k.addEventListener('input', function () { m.key = k.value.replace(/[^a-z0-9_]/gi, ''); });
			var s = input(m.source, 'منبع: result_label | score:dim | answer:fid');
			s.addEventListener('input', function () { m.source = s.value; });
			var del = iconBtn('del', 'حذف متا', 'pq-danger');
			del.addEventListener('click', function () { S.actions.meta.splice(mi, 1); renderCanvas(); });
			row.appendChild(k);
			row.appendChild(s);
			row.appendChild(del);
			mw.appendChild(row);
		});
		var add = btn('+ ذخیره متا');
		add.addEventListener('click', function () {
			S.actions.meta.push({ key: '', source: 'result_label' });
			renderCanvas();
		});
		mw.appendChild(add);
		c.appendChild(wrapLbl('نگاشت متاهای کاربر', mw));

		var sms = el('input');
		sms.type = 'checkbox';
		sms.checked = !!S.actions.sms;
		sms.addEventListener('change', function () { S.actions.sms = sms.checked ? 1 : 0; });
		c.appendChild(wrapLbl('ارسال پیامک پس از ثبت', sms));

		var rd = input(S.actions.redirect, 'آدرس ریدایرکت (اختیاری)');
		rd.addEventListener('input', function () { S.actions.redirect = rd.value; });
		c.appendChild(wrapLbl('ریدایرکت بعد از ثبت', rd));

		root.appendChild(c);
	}
	/* ---------------- مدیریت مولفه‌ها (Dimensions) ---------------- */
	function renderDimensionsManager() {
		var c = el('div', 'pq-card pq-dimensions-card');
		c.appendChild(el('h3', 'pq-card-title', '🧩 مولفه‌ها (Dimensions)'));
		c.appendChild(el('p', 'pq-card-desc', 'نام فارسی مولفه‌ها را تغییر دهید. کلید انگلیسی ثابت می‌ماند و موتور با آن کار می‌کند.'));

		// مولفه‌های پیش‌فرض مهدیار
		var defaultDims = {
			fiqh: 'فقه زندگی',
			belief: 'بینش اعتقادی',
			growth: 'رشد فردی',
			mission: 'کشف رسالت'
		};

		// اگر قبلاً ذخیره شده، از config بخوان
		if (!S.config.dimensions) {
			S.config.dimensions = defaultDims;
		}

		var table = el('table', 'pq-dims-table');
		var thead = el('thead');
		thead.innerHTML = '<tr><th>کلید (انگلیسی)</th><th>نام فارسی</th></tr>';
		table.appendChild(thead);

		var tbody = el('tbody');
		Object.keys(S.config.dimensions).forEach(function (key) {
			var tr = el('tr');
			var tdKey = el('td', 'pq-dim-key');
			tdKey.innerHTML = '<code>' + esc(key) + '</code>';
			var tdName = el('td');
			var nameInput = input(S.config.dimensions[key], 'نام فارسی');
			nameInput.addEventListener('input', function () {
				S.config.dimensions[key] = nameInput.value;
			});
			tdName.appendChild(nameInput);
			tr.appendChild(tdKey);
			tr.appendChild(tdName);
			tbody.appendChild(tr);
		});
		table.appendChild(tbody);
		c.appendChild(table);

		root.appendChild(c);
	}

	/* ---------------- مدیریت شخصیت‌ها (Personalities) ---------------- */
	/* ---------------- مدیریت شخصیت‌ها (Personalities) ---------------- */
	function renderPersonalitiesManager() {
		var c = el('div', 'pq-card pq-personalities-card');
		c.appendChild(el('h3', 'pq-card-title', '🎭 شخصیت‌ها (Personalities)'));
		c.appendChild(el('p', 'pq-card-desc', 'برای هر شخصیت، عنوان و متن توضیحی (با فرمت Rich Text) را بنویسید.'));

		var defaultPersonalities = [
			{ key: 'fiqh_belief',   title: 'نگهبانِ باور' },
			{ key: 'fiqh_growth',   title: 'راه بلد رشد' },
			{ key: 'fiqh_mission',  title: 'مرزبان آینده' },
			{ key: 'belief_fiqh',   title: 'مؤمنِ آگاه' },
			{ key: 'belief_growth', title: 'آگاه پیشرو' },
			{ key: 'belief_mission',title: 'راهبرِ معنا' },
			{ key: 'growth_fiqh',   title: 'مهندس زندگی' },
			{ key: 'growth_belief', title: 'کاوشگر بصیر' },
			{ key: 'growth_mission',title: 'معمارِ آینده' },
			{ key: 'mission_fiqh',  title: 'رسالت‌مدار' },
			{ key: 'mission_belief',title: 'راهبرِ حقیقت' },
			{ key: 'mission_growth',title: 'سازنده‌ی مسیر' }
		];

		if (!S.config.personalities) {
			S.config.personalities = {};
		}

		defaultPersonalities.forEach(function (p) {
			if (!S.config.personalities[p.key]) {
				S.config.personalities[p.key] = {
					title: p.title,
					content: ''
				};
			}

			// 🔄 Backward compatibility: اگر ساختار قدیمی texts وجود دارد، به content مهاجرت بده
			if (S.config.personalities[p.key].texts && !S.config.personalities[p.key].content) {
				var oldTexts = S.config.personalities[p.key].texts;
				var migrated = '';
				if (oldTexts.character) migrated += '<h4>🪞 کاراکتر تو</h4><p>' + esc(oldTexts.character) + '</p>';
				if (oldTexts.treasure)  migrated += '<h4>💎 گنجینه‌ی درون</h4><p>' + esc(oldTexts.treasure) + '</p>';
				if (oldTexts.compass)   migrated += '<h4>🧭 قطب‌نمای پنهان</h4><p>' + esc(oldTexts.compass) + '</p>';
				if (oldTexts.call)      migrated += '<h4>🚀 دعوت به قهرمانی</h4><p>' + esc(oldTexts.call) + '</p>';
				S.config.personalities[p.key].content = migrated;
				delete S.config.personalities[p.key].texts;
			}

			var persCard = el('div', 'pq-person-card');
			var persHead = el('div', 'pq-person-head');
			persHead.innerHTML = '<span class="pq-person-key">' + esc(p.key) + '</span><span class="pq-person-title">' + esc(S.config.personalities[p.key].title || p.title) + '</span>';
			persCard.appendChild(persHead);

			// عنوان شخصیت
			var titleInput = input(S.config.personalities[p.key].title, 'عنوان شخصیت');
			titleInput.addEventListener('input', function () {
				S.config.personalities[p.key].title = titleInput.value;
				// به‌روزرسانی عنوان در هدر
				var titleSpan = persCard.querySelector('.pq-person-title');
				if (titleSpan) { titleSpan.textContent = titleInput.value || p.title; }
			});
			persCard.appendChild(wrapLbl('عنوان شخصیت', titleInput));

			// 🆕 ویرایشگر محتوا (textarea که به TinyMCE تبدیل می‌شود)
			var editorId = 'pq-person-editor-' + p.key;
			var ta = el('textarea', 'pq-person-editor');
			ta.id = editorId;
			ta.value = S.config.personalities[p.key].content || '';
			ta.addEventListener('input', function () {
				S.config.personalities[p.key].content = ta.value;
			});
			persCard.appendChild(wrapLbl('متن شخصیت (HTML)', ta));

			c.appendChild(persCard);
		});

		root.appendChild(c);

		// 🆕 TinyMCE را بعد از render راه‌اندازی کن
		setTimeout(function () {
			if (window.tinyMCE) {
				document.querySelectorAll('.pq-person-editor').forEach(function (ta) {
					// اگر قبلاً instance وجود دارد، حذف کن
					if (window.tinyMCE.get(ta.id)) {
						window.tinyMCE.remove(ta.id);
					}

								window.tinyMCE.init({
						selector: '#' + ta.id,
						height: 320,
						menubar: false,
						directionality: 'rtl',
						language: 'fa',
						// plugins بدون 'code' (در وردپرس ۵+ نیست)
						plugins: 'lists link image table hr wordcount',
						toolbar: 'undo redo | formatselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | removeformat',
						content_style: 'body { font-family: Tahoma, Arial, sans-serif; font-size: 14px; direction: rtl; line-height: 1.8; padding: 10px; }',
						branding: false,
						// جلوگیری از بارگذاری افزونه‌های غیرموجود
						external_plugins: {},
						setup: function (editor) {
							var thisId = editor.id;
							editor.on('change keyup NodeChange', function () {
								var key = thisId.replace('pq-person-editor-', '');
								if (S.config.personalities && S.config.personalities[key]) {
									S.config.personalities[key].content = editor.getContent();
								}
							});
							// در زمان init، مقدار اولیه را set کن
							editor.on('init', function () {
								var key = thisId.replace('pq-person-editor-', '');
								if (S.config.personalities && S.config.personalities[key]) {
									editor.setContent(S.config.personalities[key].content || '');
								}
							});
						}
					});
				});
			}
		}, 200);
	}
	/* ---------------- سریالایز ---------------- */
	/* ---------------- سریالایز ---------------- */
	var form = document.getElementById('pq-builder-form');
	if (form) {
		form.addEventListener('submit', function () {
		    			// 🆕 قبل از submit، محتوای نهایی را از TinyMCE بگیر
			if (window.tinyMCE && typeof window.tinyMCE.get === 'function') {
				document.querySelectorAll('.pq-person-editor').forEach(function (ta) {
					var editor = window.tinyMCE.get(ta.id);
					if (editor && typeof editor.getContent === 'function') {
						var key = ta.id.replace('pq-person-editor-', '');
						if (S.config.personalities && S.config.personalities[key]) {
							S.config.personalities[key].content = editor.getContent();
						}
					}
				});
			}
			renumberQuestions();
			document.getElementById('pq-schema-json').value = JSON.stringify(S.schema);
			document.getElementById('pq-actions-json').value = JSON.stringify(S.actions);
			document.getElementById('pq-engine-config-json').value = JSON.stringify(S.config || {});
			// موتور آزمون را هم ذخیره کن
			var engineInput = document.getElementById('pq-engine');
			if (engineInput) { engineInput.value = S.engine || 'none'; }
		});
	}

	/* ---------------- Init ---------------- */
	renderToolbox();
	renderCanvas();
})();
