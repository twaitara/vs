<?php
/** Portal page: bank users scan a report's QR code to verify it. */
require_once __DIR__ . '/portal_layout.php';
require_client();

portal_header('Verify a Report', 'verify');
?>
<h1 class="pt">Verify a Report</h1>
<p class="psub">Point your camera at the QR code on a Kennet valuation report to confirm it is genuine.</p>

<div class="card" style="max-width:560px">
  <div id="scanWrap" style="position:relative;background:#000;border-radius:10px;overflow:hidden;aspect-ratio:1/1;max-width:360px;margin:0 auto">
    <video id="scanVid" playsinline muted style="width:100%;height:100%;object-fit:cover"></video>
    <div style="position:absolute;inset:12%;border:3px solid rgba(255,255,255,.8);border-radius:12px;pointer-events:none"></div>
  </div>
  <div id="scanMsg" class="muted" style="font-size:13px;margin-top:10px;text-align:center">Starting camera…</div>
  <div style="display:flex;gap:8px;justify-content:center;margin-top:10px;flex-wrap:wrap">
    <button class="btn" type="button" id="scanStart"><i data-lucide="camera"></i> Start camera</button>
    <label class="btn sec" style="cursor:pointer"><i data-lucide="image"></i> Use a photo
      <input type="file" id="scanFile" accept="image/*" capture="environment" style="display:none"></label>
  </div>
  <canvas id="scanCanvas" style="display:none"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script>
(function(){
  var vid=document.getElementById('scanVid'), cv=document.getElementById('scanCanvas'),
      msg=document.getElementById('scanMsg'), startBtn=document.getElementById('scanStart'),
      fileInp=document.getElementById('scanFile'), stream=null, scanning=false;
  var VERIFY='<?= url('verify.php') ?>';

  function isVerify(t){ return typeof t==='string' && t.indexOf('/verify.php?')>=0; }
  function go(text){ scanning=false; stop(); if(isVerify(text)){ msg.textContent='Opening result…'; location.href=text; }
    else { msg.textContent='That QR code is not a Kennet report code.'; } }
  function stop(){ if(stream){ stream.getTracks().forEach(function(t){t.stop();}); stream=null; } }

  function tick(){
    if(!scanning) return;
    if(vid.readyState===vid.HAVE_ENOUGH_DATA && window.jsQR){
      cv.width=vid.videoWidth; cv.height=vid.videoHeight;
      var cx=cv.getContext('2d'); cx.drawImage(vid,0,0,cv.width,cv.height);
      try{ var img=cx.getImageData(0,0,cv.width,cv.height); var r=jsQR(img.data,img.width,img.height); if(r&&r.data){ go(r.data); return; } }catch(e){}
    }
    requestAnimationFrame(tick);
  }
  function start(){
    if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){ msg.textContent='Camera not available — use “Use a photo”.'; return; }
    msg.textContent='Starting camera…';
    navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}}).then(function(s){
      stream=s; vid.srcObject=s; vid.play(); scanning=true; msg.textContent='Point at the QR code…'; requestAnimationFrame(tick);
    }).catch(function(){ msg.textContent='Could not open the camera — allow access, or use “Use a photo”.'; });
  }
  startBtn.addEventListener('click', start);
  fileInp.addEventListener('change', function(){
    var f=fileInp.files&&fileInp.files[0]; if(!f) return; msg.textContent='Reading photo…';
    var img=new Image(); img.onload=function(){ cv.width=img.naturalWidth; cv.height=img.naturalHeight; var cx=cv.getContext('2d'); cx.drawImage(img,0,0);
      try{ var d=cx.getImageData(0,0,cv.width,cv.height); var r=window.jsQR&&jsQR(d.data,d.width,d.height); if(r&&r.data){ go(r.data); } else { msg.textContent='No QR code found in that photo — try again.'; } }catch(e){ msg.textContent='Could not read that photo.'; } };
    img.onerror=function(){ msg.textContent='Could not open that image.'; }; img.src=URL.createObjectURL(f);
  });
  // auto-start if the library is ready
  if(window.jsQR) start(); else window.addEventListener('load', function(){ setTimeout(function(){ if(window.jsQR) start(); else msg.textContent='Tap “Start camera”.'; }, 400); });
})();
</script>
<?php portal_footer();
