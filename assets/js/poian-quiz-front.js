/**
 * Poian Quiz — Front (IIFE) | چندصفحه‌ای + شرط‌ها + DnD + ثبت REST
 */
(function () {
	'use strict';
	var app = document.querySelector('.pq-app');
	if (!app) { return; }

	var CFG = {
		form: app.getAttribute('data-form'),
		rest: app.getAttribute('data-rest'),
		retake: app.getAttribute('data-retake'),
		nonce: app.getAttribute('data-nonce'),
		wpNonce: app.getAttribute('data-wpnonce'),
		single: app.getAttribute('data-display') === 'single',
		progress: app.getAttribute('data-progress') === '1'
	};

	function $$(s, c) { return Array.prototype.slice.call((c || app).querySelectorAll(s)); }
	function $(s, c) { return (c || app).querySelector(s); }

	/* ---------------- Toast ---------------- */
	var toastEl = null, toastT = null;
	function toast(msg, err) {
		if (!toastEl) {
			toastEl = document.createElement('div');
			toastEl.className = 'pq-toast';
			toastEl.setAttribute('role', 'alert');
			app.appendChild(toastEl);
		}
		toastEl.textContent = msg;
		toastEl.classList.toggle('pq-err', !!err);
		toastEl.style.setProperty('display', 'block', 'important');
		toastEl.classList.add('pq-show');
		clearTimeout(toastT);
		toastT = setTimeout(function () {
			toastEl.classList.remove('pq-show');
			setTimeout(function () { toastEl.style.setProperty('display', 'none', 'important'); }, 300);
		}, 4200);
	}

	var pages = $$('.pq-page');
	var current = 0;

	/* ---------------- مقدار فیلدها ---------------- */
	function fieldType(el) { return el.getAttribute('data-type'); }
	function isAnswerable(el) { var t = fieldType(el); return t && t !== 'description' && t !== 'heading'; }
	function fieldValue(el) {
		var t = fieldType(el);
		if (t === 'radio') { var r = el.querySelector('input:checked'); return r ? r.value : ''; }
		if (t === 'checkbox') { return $$('input:checked', el).map(function (i) { return i.value; }); }
		if (t === 'rank') { return $$('.pq-option', el).map(function (o) { return o.getAttribute('data-opt'); }); }
		var i2 = el.querySelector('input,textarea');
		return i2 ? i2.value : '';
	}
	function isEmptyV(v) { return v === '' || (Array.isArray(v) && v.length === 0); }

	/* ---------------- شرط‌ها (آینه سرور) ---------------- */
	function condMap() {
		var m = {};
		$$('.pq-field[data-fid]').forEach(function (f) { m[f.getAttribute('data-fid')] = fieldValue(f); });
		return m;
	}
	
	function condMet(conds, m, logic) {
		if (!conds || !conds.length) { return true; }
		logic = logic || 'all';
		var results = [];
		for (var i = 0; i < conds.length; i++) {
			results.push(evalOneCond(conds[i], m));
		}
		return logic === 'any'
			? results.indexOf(true) !== -1
			: results.indexOf(false) === -1;
	}
	function evalOneCond(c, m) {
		var fv = m[c.field];
		var vals = c.values || [];
		var op = c.op || 'is';

		// نرمال‌سازی fv به آرایه string
		var fvArr;
		if (Array.isArray(fv)) {
			fvArr = fv.map(function (v) { return String(v); });
		} else if (fv === null || fv === undefined || fv === '') {
			fvArr = [];
		} else {
			fvArr = [String(fv)];
		}
		var isEmpty = fvArr.length === 0;

		switch (op) {
			case 'is':
				return fvArr.some(function (v) { return vals.indexOf(v) !== -1; });
			case 'not':
				return !fvArr.some(function (v) { return vals.indexOf(v) !== -1; });
			case 'empty':
				return isEmpty;
			case 'not_empty':
				return !isEmpty;
			case 'contains':
				return fvArr.some(function (v) {
					var lc = v.toLowerCase();
					return vals.some(function (n) { return n !== '' && lc.indexOf(n.toLowerCase()) !== -1; });
				});
			case 'not_contains':
				return !fvArr.some(function (v) {
					var lc = v.toLowerCase();
					return vals.some(function (n) { return n !== '' && lc.indexOf(n.toLowerCase()) !== -1; });
				});
			case 'starts_with':
				return fvArr.some(function (v) {
					return vals.some(function (p) { return p !== '' && v.toLowerCase().indexOf(p.toLowerCase()) === 0; });
				});
			case 'ends_with':
				return fvArr.some(function (v) {
					return vals.some(function (s) {
						return s !== '' && v.length >= s.length && v.toLowerCase().slice(-s.length) === s.toLowerCase();
					});
				});
			case 'gt': case 'gte': case 'lt': case 'lte':
				var num = (fvArr[0] !== undefined && !isNaN(parseFloat(fvArr[0]))) ? parseFloat(fvArr[0]) : null;
				var cmp = (vals[0] !== undefined && !isNaN(parseFloat(vals[0]))) ? parseFloat(vals[0]) : null;
				if (num === null || cmp === null) { return false; }
				if (op === 'gt')  { return num >  cmp; }
				if (op === 'gte') { return num >= cmp; }
				if (op === 'lt')  { return num <  cmp; }
				if (op === 'lte') { return num <= cmp; }
				return false;
			default:
				return true;
		}
	}
	function updateConditions() {
		var m = condMap();
		$$('.pq-field[data-cond]').forEach(function (f) {
			try {
				var payload = JSON.parse(f.getAttribute('data-cond'));
				var conds = payload.conditions || [];
				var logic = payload.logic || 'all';
				var action = payload.action || 'show';

				var condOk = condMet(conds, m, logic);
				var shouldShow = (action === 'hide') ? !condOk : condOk;
				f.classList.toggle('pq-hidden', !shouldShow);
			} catch (e) {
				// ignore invalid JSON
			}
		});
	}

	/* ---------------- پیشرفت ---------------- */
	function updateProgress() {
		if (!CFG.progress) { return; }
		var fill = $('.pq-progress-fill'), cur = $('.pq-progress-current'), tot = $('.pq-progress-total');
		if (!fill) { return; }

		var done, totalV;

		// اگر چند صفحه داریم، بر اساس صفحات محاسبه کن
		if (pages.length > 1) {
			done = current + 1;
			totalV = pages.length;
		} else {
			// تک صفحه: بر اساس فیلدها
			var fields = $$('.pq-field[data-fid]').filter(function (f) {
				return isAnswerable(f) && !f.classList.contains('pq-hidden');
			});
			done = fields.filter(function (f) {
				if (fieldType(f) === 'rank') { return f.classList.contains('pq-touched'); }
				return !isEmptyV(fieldValue(f));
			}).length;
			totalV = fields.length;
		}

		fill.style.width = (totalV ? (done / totalV) * 100 : 0) + '%';
		if (cur) { cur.textContent = done; }
		if (tot) { tot.textContent = totalV; }
	}
	/* ---------------- ناوبری صفحه‌ها ---------------- */
	function showPage(i) {
		current = Math.max(0, Math.min(pages.length - 1, i));
		pages.forEach(function (p, idx) { p.classList.toggle('pq-hidden', idx !== current); });

		var prev = $('.pq-prev'), next = $('.pq-next'), sub = $('.pq-submit');
		if (prev) {
			prev.classList.toggle('pq-hidden', current === 0);
			// متن دکمه قبلی از صفحه فعلی
			var prevText = pages[current].getAttribute('data-prev');
			if (prevText) { prev.querySelector('span').textContent = prevText; }
		}
		if (next) {
			next.classList.toggle('pq-hidden', current === pages.length - 1);
			// متن دکمه بعدی از صفحه فعلی
			var nextText = pages[current].getAttribute('data-next');
			if (nextText) { next.querySelector('span').textContent = nextText; }
		}
		if (sub) { sub.classList.toggle('pq-hidden', current !== pages.length - 1); }
		updateProgress();
		updateConditions();

		// اسکرول به بالای صفحه جدید
		var form = $('#pq-form');
		if (form) {
			setTimeout(function () {
				form.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}, 100);
		}
	}

	/**
	 * اعتبارسنجی فیلدهای الزامی صفحه فعلی.
	 * @returns {boolean} true اگر همه فیلدهای الزامی پر شده باشند
	 */
	function validateCurrentPage() {
		var currentPage = pages[current];
		if (!currentPage) { return true; }

		var missing = false;
		var firstMissing = null;

		$$('.pq-field[data-fid]', currentPage).forEach(function (f) {
			if (!isAnswerable(f) || f.classList.contains('pq-hidden')) { return; }
			if (f.getAttribute('data-required') === '1' && isEmptyV(fieldValue(f))) {
				f.classList.add('pq-missing');
				missing = true;
				if (!firstMissing) { firstMissing = f; }
			} else {
				f.classList.remove('pq-missing');
			}
		});

		if (missing && firstMissing) {
			// اسکرول به اولین فیلد خالی
			firstMissing.scrollIntoView({ behavior: 'smooth', block: 'center' });
			toast('لطفاً فیلدهای الزامی را تکمیل کنید.', true);
		}

		return !missing;
	}
	if (pages.length > 1) {
		var pb = $('.pq-prev'), nb = $('.pq-next');
		if (pb) {
			pb.addEventListener('click', function () {
				showPage(current - 1);
			});
		}
		if (nb) {
			nb.addEventListener('click', function () {
				// اعتبارسنجی صفحه فعلی قبل از رفتن به بعد
				if (validateCurrentPage()) {
					showPage(current + 1);
				}
			});
		}
		showPage(0);
	} else {
		var pb2 = $('.pq-prev'), nb2 = $('.pq-next');
		if (pb2) { pb2.classList.add('pq-hidden'); }
		if (nb2) { nb2.classList.add('pq-hidden'); }
	}

	/* ---------------- DnD رتبه‌بندی ---------------- */
	var drag = null;
	app.addEventListener('pointerdown', function (e) {
		var h = e.target.closest('.pq-handle');
		if (!h || e.button) { return; }
		var opt = h.closest('.pq-option');
		if (!opt) { return; }
		e.preventDefault();
		h.setPointerCapture(e.pointerId);
		drag = { opt: opt, list: opt.parentElement, id: e.pointerId };
		opt.classList.add('pq-dragging');
		var fld = opt.closest('.pq-field');
		if (fld) { fld.classList.add('pq-touched'); }
	});
	app.addEventListener('pointermove', function (e) {
		if (!drag || e.pointerId !== drag.id) { return; }
		var siblings = $$('.pq-option', drag.list).filter(function (el) { return el !== drag.opt; });
		for (var i = 0; i < siblings.length; i++) {
			var r = siblings[i].getBoundingClientRect();
			if (e.clientY < r.top + r.height / 2) {
				if (siblings[i].previousElementSibling !== drag.opt) { drag.list.insertBefore(drag.opt, siblings[i]); }
				return;
			}
		}
		if (drag.list.lastElementChild !== drag.opt) { drag.list.appendChild(drag.opt); }
	});
	function endDrag(e) {
		if (!drag || (e && e.pointerId !== drag.id)) { return; }
		drag.opt.classList.remove('pq-dragging');
		drag = null;
		updateProgress();
	}
	app.addEventListener('pointerup', endDrag);
	app.addEventListener('pointercancel', endDrag);

	app.addEventListener('click', function (e) {
		var b = e.target.closest('.pq-move');
		if (!b) { return; }
		var opt = b.closest('.pq-option'), list = opt.parentElement;
		if (b.classList.contains('pq-up')) { if (opt.previousElementSibling) { list.insertBefore(opt, opt.previousElementSibling); } }
		else if (opt.nextElementSibling) { list.insertBefore(opt.nextElementSibling, opt); }
		var fld = opt.closest('.pq-field');
		if (fld) { fld.classList.add('pq-touched'); }
		updateProgress();
	});

	/* ---------------- رویدادهای زنده ---------------- */
	app.addEventListener('change', function () { updateConditions(); updateProgress(); });
	app.addEventListener('input', function (e) {
		if (e.target.matches && e.target.matches('input[type="text"],textarea')) { updateConditions(); updateProgress(); }
	});

	/* ---------------- ثبت ---------------- */
	var submitBtn = $('.pq-submit');
	if (submitBtn) {
		submitBtn.addEventListener('click', function () {
			updateConditions();

			// اعتبارسنجی همه فیلدهای همه صفحات
			var missing = false;
			var firstMissing = null;
			var firstMissingPage = null;

			pages.forEach(function (page, pageIdx) {
				$$('.pq-field[data-fid]', page).forEach(function (f) {
					if (!isAnswerable(f) || f.classList.contains('pq-hidden')) { return; }
					if (f.getAttribute('data-required') === '1' && isEmptyV(fieldValue(f))) {
						f.classList.add('pq-missing');
						missing = true;
						if (!firstMissing) {
							firstMissing = f;
							firstMissingPage = pageIdx;
						}
					} else {
						f.classList.remove('pq-missing');
					}
				});
			});

			if (missing) {
				// اگر فیلد خالی در صفحه دیگری است، به آن صفحه برو
				if (firstMissingPage !== null && firstMissingPage !== current) {
					showPage(firstMissingPage);
					setTimeout(function () {
						if (firstMissing) {
							firstMissing.scrollIntoView({ behavior: 'smooth', block: 'center' });
						}
					}, 300);
				} else if (firstMissing) {
					firstMissing.scrollIntoView({ behavior: 'smooth', block: 'center' });
				}
				toast('لطفاً فیلدهای الزامی را تکمیل کنید.', true);
				return;
			}

			if (submitBtn.classList.contains('pq-loading')) { return; }
			submitBtn.classList.add('pq-loading');

			// جمع کردن همه فیلدهای همه صفحات
			var answers = {};
			pages.forEach(function (page) {
				$$('.pq-field[data-fid]', page).forEach(function (f) {
					if (!isAnswerable(f) || f.classList.contains('pq-hidden')) { return; }
					answers[f.getAttribute('data-fid')] = fieldValue(f);
				});
			});

			fetch(CFG.rest, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.wpNonce, 'X-Poian-Nonce': CFG.nonce },
				body: JSON.stringify({ form_id: parseInt(CFG.form, 10), answers: answers })
			})
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (json && json.success) {
						var d = json.data || {};
						if (d.redirect) { window.location.href = d.redirect; return; }
						window.location.reload();
					} else {
						toast(json && json.message ? json.message : 'خطا در ثبت.', true);
						submitBtn.classList.remove('pq-loading');
					}
				})
				.catch(function () { toast('خطا در ارتباط با سرور.', true); submitBtn.classList.remove('pq-loading'); });
		});
	}
	/* ---------------- آزمون مجدد ---------------- */
	var retakeBtn = $('.pq-retake');
	if (retakeBtn) {
		var retakeMode = app.getAttribute('data-retake-mode') || 'cooldown';

		retakeBtn.addEventListener('click', function () {
			if (retakeBtn.classList.contains('pq-loading')) { return; }

			// در حالت once، دکمه retake نباید وجود داشته باشد (از قبل چک شده)
			if (retakeMode === 'once') { return; }

			retakeBtn.classList.add('pq-loading');
			fetch(CFG.retake, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.wpNonce, 'X-Poian-Nonce': CFG.nonce },
				body: JSON.stringify({ form_id: parseInt(CFG.form, 10) })
			})
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (json && json.success) { window.location.reload(); }
					else { toast(json && json.message ? json.message : 'خطا.', true); retakeBtn.classList.remove('pq-loading'); }
				})
				.catch(function () { toast('خطا در ارتباط.', true); retakeBtn.classList.remove('pq-loading'); });
		});
	}
	/* ---------------- Custom Select (div > ul > li) ---------------- */

	function enhanceSelects() {
		$$('select.pq-input, select[data-pq-select]').forEach(function (sel) {
			if (sel.dataset.pqEnhanced) { return; } // قبلاً enhance شده
			sel.dataset.pqEnhanced = '1';

			var wrapper = document.createElement('div');
			wrapper.className = 'pq-select';

			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'pq-select-btn';
			btn.setAttribute('aria-haspopup', 'listbox');
			btn.setAttribute('aria-expanded', 'false');

			var btnText = document.createElement('span');
			btnText.className = 'pq-select-text';
			var selectedOpt = sel.options[sel.selectedIndex];
			btnText.textContent = selectedOpt ? selectedOpt.text : '— انتخاب کنید —';

			var arrow = document.createElement('span');
			arrow.className = 'pq-select-arrow';
			arrow.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z"/></svg>';

			btn.appendChild(btnText);
			btn.appendChild(arrow);

			var list = document.createElement('ul');
			list.className = 'pq-select-list';
			list.setAttribute('role', 'listbox');
			list.tabIndex = -1;

			Array.prototype.forEach.call(sel.options, function (opt) {
				var li = document.createElement('li');
				li.className = 'pq-select-item';
				li.setAttribute('role', 'option');
				li.setAttribute('data-value', opt.value);
				li.textContent = opt.text;
				if (opt.selected) {
					li.classList.add('pq-selected');
					li.setAttribute('aria-selected', 'true');
				}
				list.appendChild(li);
			});

			wrapper.appendChild(btn);
			wrapper.appendChild(list);

			// select اصلی را مخفی کن و wrapper را جایگزین کن
			sel.style.display = 'none';
			sel.parentNode.insertBefore(wrapper, sel);

			/* رویدادها */
			btn.addEventListener('click', function () {
				var isOpen = wrapper.classList.contains('pq-open');
				closeAllSelects();
				if (!isOpen) {
					wrapper.classList.add('pq-open');
					btn.setAttribute('aria-expanded', 'true');
					list.focus();
				}
			});

			list.addEventListener('click', function (e) {
				var li = e.target.closest('.pq-select-item');
				if (!li) { return; }
				selectItem(li);
			});

			/* Keyboard navigation */
			list.addEventListener('keydown', function (e) {
				var items = Array.prototype.slice.call(list.querySelectorAll('.pq-select-item'));
				var current = list.querySelector('.pq-selected') || items[0];
				var idx = items.indexOf(current);

				if (e.key === 'ArrowDown') {
					e.preventDefault();
					idx = Math.min(idx + 1, items.length - 1);
					items[idx].focus();
				} else if (e.key === 'ArrowUp') {
					e.preventDefault();
					idx = Math.max(idx - 1, 0);
					items[idx].focus();
				} else if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					if (document.activeElement.classList.contains('pq-select-item')) {
						selectItem(document.activeElement);
					}
				} else if (e.key === 'Escape') {
					closeSelect(wrapper);
					btn.focus();
				}
			});

			function selectItem(li) {
				// sync با select اصلی (برای submit فرم)
				sel.value = li.getAttribute('data-value');
				sel.dispatchEvent(new Event('change', { bubbles: true }));

				// update UI
				btnText.textContent = li.textContent;
				list.querySelectorAll('.pq-select-item').forEach(function (item) {
					item.classList.remove('pq-selected');
					item.removeAttribute('aria-selected');
				});
				li.classList.add('pq-selected');
				li.setAttribute('aria-selected', 'true');

				closeSelect(wrapper);
				btn.focus();
			}
		});
	}

	function closeAllSelects() {
		$$('.pq-select.pq-open').forEach(function (w) {
			closeSelect(w);
		});
	}

	function closeSelect(wrapper) {
		wrapper.classList.remove('pq-open');
		var btn = wrapper.querySelector('.pq-select-btn');
		if (btn) { btn.setAttribute('aria-expanded', 'false'); }
	}

	/* بستن با کلیک بیرون */
	document.addEventListener('click', function (e) {
		if (!e.target.closest('.pq-select')) {
			closeAllSelects();
		}
	});
	updateConditions();
	updateProgress();
	enhanceSelects();
})();
