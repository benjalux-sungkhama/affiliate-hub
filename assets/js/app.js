/* AffiliateHub — สคริปต์ฝั่งหน้าเว็บ */
(function () {
  'use strict';

  // ยืนยันการลบ 2 ชั้น
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-confirm]');
    if (el && !window.confirm(el.getAttribute('data-confirm'))) {
      e.preventDefault();
    }
  });

  // ตัวช่วยเพิ่ม/ลบแถวซีนในฟอร์มสูตร
  window.AFH = window.AFH || {};

  window.AFH.addScene = function (tbodyId) {
    var tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    var idx = tbody.querySelectorAll('.scene-row').length + 1;
    var row = document.createElement('div');
    row.className = 'scene-row';
    row.innerHTML =
      '<input name="seq[]" value="' + idx + '" title="ลำดับ">' +
      '<input name="time_from[]" placeholder="เริ่ม" value="0">' +
      '<input name="time_to[]" placeholder="จบ" value="0">' +
      '<input name="description[]" placeholder="เนื้อหาซีน">' +
      '<input name="camera_angle[]" placeholder="มุมกล้อง">' +
      '<input name="overlay_text[]" placeholder="ข้อความบนจอ">';
    tbody.appendChild(row);
  };

  // ปิดเมนูมือถือเมื่อคลิกลิงก์
  document.querySelectorAll('.nav-item').forEach(function (a) {
    a.addEventListener('click', function () { document.body.classList.remove('nav-open'); });
  });
})();
