/* APEXSUITE photo->text prototype: snap chassis (VIN) / odometer and read it into the field.
   Uses Tesseract.js (lazy-loaded). Always human-confirmed; flags low-confidence / unreadable shots. */
(function () {
  var TESS = 'https://unpkg.com/tesseract.js@5/dist/tesseract.min.js';

  function loadTesseract(cb) {
    if (window.Tesseract) return cb(true);
    var s = document.createElement('script');
    s.src = TESS; s.onload = function () { cb(true); }; s.onerror = function () { cb(false); };
    document.head.appendChild(s);
  }

  // ISO-3779 VIN check digit — lets us actually VERIFY a chassis read.
  function vinValid(v) {
    if (!/^[A-HJ-NPR-Z0-9]{17}$/.test(v)) return false;
    var map = {A:1,B:2,C:3,D:4,E:5,F:6,G:7,H:8,J:1,K:2,L:3,M:4,N:5,P:7,R:9,S:2,T:3,U:4,V:5,W:6,X:7,Y:8,Z:9};
    var w = [8,7,6,5,4,3,2,10,0,9,8,7,6,5,4,3,2], sum = 0;
    for (var i = 0; i < 17; i++) { var ch = v[i]; sum += (/\d/.test(ch) ? +ch : map[ch]) * w[i]; }
    var chk = sum % 11, c = chk === 10 ? 'X' : '' + chk;
    return v[8] === c;
  }

  function status(name, msg, kind) {
    var el = document.querySelector('.ocr-status[data-ocr-status="' + name + '"]');
    if (!el) return;
    el.textContent = msg; el.className = 'ocr-status ' + (kind || '');
  }

  function drawScaled(img, max) {
    var w = img.naturalWidth, h = img.naturalHeight, s = Math.min(1, max / Math.max(w, h));
    var cv = document.createElement('canvas'); cv.width = w * s; cv.height = h * s;
    var cx = cv.getContext('2d'); cx.drawImage(img, 0, 0, cv.width, cv.height);
    return cv;
  }

  function handle(name, mode, file) {
    status(name, 'Reading photo…', 'busy');
    var img = new Image();
    img.onload = function () {
      var canvas = drawScaled(img, 1400);
      loadTesseract(function (ok) {
        if (!ok) { status(name, 'Could not load the scanner (check your connection).', 'err'); return; }
        window.Tesseract.recognize(canvas, 'eng').then(function (res) {
          var data = res.data || {};
          var conf = Math.round(data.confidence || 0);
          var raw = (data.text || '').toUpperCase();
          var field = document.querySelector('[name="' + name + '"]');
          if (mode === 'vin') {
            var cleaned = raw.replace(/[^A-HJ-NPR-Z0-9]/g, '');
            var cand = '';
            for (var i = 0; i + 17 <= cleaned.length; i++) {
              var chunk = cleaned.substr(i, 17);
              if (vinValid(chunk)) { cand = chunk; break; }
              if (!cand && /^[A-HJ-NPR-Z0-9]{17}$/.test(chunk)) cand = chunk; // keep first 17-run as fallback
            }
            if (!cand) { status(name, "Couldn't read a chassis number clearly — retake, closer & steady.", 'err'); return; }
            if (field) field.value = cand;
            if (vinValid(cand)) status(name, '✓ Verified chassis number. Please confirm.', 'ok');
            else status(name, 'Read but could not verify (' + conf + '%). Check it carefully.', 'warn');
          } else {
            var groups = raw.replace(/[OQ]/g, '0').replace(/[IL]/g, '1').match(/\d[\d,\. ]{1,}/g) || [];
            groups = groups.map(function (g) { return g.replace(/[^\d]/g, ''); }).filter(function (g) { return g.length >= 2; });
            groups.sort(function (a, b) { return b.length - a.length; });
            var num = groups[0] || '';
            if (!num || conf < 45) { status(name, "Couldn't read the number clearly (" + conf + '%) — retake.', 'err'); return; }
            if (field) field.value = num;
            status(name, 'Read “' + num + '” (' + conf + '% sure). Please confirm.', conf >= 70 ? 'ok' : 'warn');
          }
          if (field) field.dispatchEvent(new Event('input'));
        }).catch(function () { status(name, 'Reading failed — try again.', 'err'); });
      });
    };
    img.onerror = function () { status(name, 'Could not open that image.', 'err'); };
    img.src = URL.createObjectURL(file);
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.ocr-btn') : null;
    if (!btn) return;
    e.preventDefault();
    var name = btn.getAttribute('data-ocr-target'), mode = btn.getAttribute('data-ocr-mode') || 'digits';
    var inp = document.createElement('input');
    inp.type = 'file'; inp.accept = 'image/*'; inp.setAttribute('capture', 'environment');
    inp.onchange = function () { if (inp.files && inp.files[0]) handle(name, mode, inp.files[0]); };
    inp.click();
  });
})();
