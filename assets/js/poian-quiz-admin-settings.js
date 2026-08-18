/**
 * Poian Quiz — Admin Settings (IIFE)
 * منطق نمایش شرطی فیلدهای تنظیمات فرم
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var table = document.getElementById('pq-form-settings-table');
		if (!table) { return; }

		var sel = table.querySelector('select[name="fs_retake_mode"]');
		if (!sel) { return; }

		function toggleRows() {
			var val = sel.value;
			var cooldownRows = table.querySelectorAll('.pq-row-cooldown');
			var perdayRows = table.querySelectorAll('.pq-row-perday');

			/*
			 * منطق نمایش:
			 * - cooldown:  همه فیلدها نمایش
			 * - unlimited: فقط سقف کل (فاصله و سقف روزانه بی‌معنی)
			 * - once:      هیچ‌کدام (کاربر فقط یک‌بار شرکت می‌کند)
			 * - inherit:   همه فیلدها (چون مقدار کلی مشخص نیست)
			 */
			var showCooldown = (val === 'cooldown' || val === 'inherit');
			var showPerday = (val === 'cooldown' || val === 'inherit');

			cooldownRows.forEach(function (row) {
				row.style.display = showCooldown ? '' : 'none';
			});
			perdayRows.forEach(function (row) {
				row.style.display = showPerday ? '' : 'none';
			});
		}

		sel.addEventListener('change', toggleRows);
		toggleRows(); // اجرای اولیه
	});
})();
