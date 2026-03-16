<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Library Attendance Kiosk</title>
  <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
  <style>
    html,body{height:100%;margin:0;font-family:Inter,system-ui,Arial,sans-serif;background:#000080;color:#fff}
    .wrap{height:100%;display:flex;align-items:center;justify-content:center;flex-direction:column}
    .card{background:rgba(255,255,255,0.03);padding:36px;border-radius:12px;min-width:320px;max-width:900px;text-align:center}
    .title{font-size:32px;margin-bottom:8px}
    .message{font-size:22px;margin:18px 0;color:#a7f3d0}
    .sub{font-size:14px;color:#9ca3af;margin-bottom:12px}
    input.scanner{font-size:20px;padding:12px 16px;border-radius:8px;border:0;outline:none;width:100%;box-sizing:border-box}
    .hidden{opacity:0;height:0;border:0;padding:0;margin:0}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div>
        <img src="/images/pshs.png" alt="PSHS Logo" style="height:400px;margin-bottom:12px">
      </div>
      <div class="title">Library Attendance Kiosk</div>
      <div class="sub">Please scan your school ID</div>
      <div id="message" class="message">Ready to scan</div>
      <input id="scanner" class="scanner" autofocus autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" />
    </div>
  </div>

  <script>
    (function(){
      const input = document.getElementById('scanner');
      const message = document.getElementById('message');
      const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

      // Focus immediately and keep focus
      function focusInput(){ input.focus(); input.select(); }
      window.addEventListener('load', focusInput);
      window.addEventListener('click', focusInput);
      setInterval(()=>{ if(document.activeElement !== input) focusInput(); }, 1000);

      let processing = false;

      async function submitValue(val){
        if (!val) return reset();
        if (processing) return;
        processing = true;
        try{
          const res = await fetch("/library/kiosk/scan", {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrf,
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ pisay: val })
          });
          const data = await res.json();
          if (res.ok && data.student_name) {
            message.textContent = `Welcome, ${data.student_name}`;
          } else if (res.ok) {
            message.textContent = `Scan recorded`;
          } else if (data && data.error) {
            message.textContent = data.error;
          } else {
            message.textContent = 'Error recording scan';
          }
        }catch(e){
          message.textContent = 'Network error';
        }
        // briefly show message then reset
        setTimeout(()=>{ reset(); processing = false; }, 1800);
      }

      function reset(){
        input.value = '';
        message.textContent = 'Ready to scan';
        focusInput();
      }

      input.addEventListener('keydown', function(e){
        if (e.key === 'Enter'){
          e.preventDefault();
          const val = input.value.trim();
          submitValue(val);
        }
      });

      // Some scanners send a suffix (CR) or paste; handle paste event
      input.addEventListener('paste', function(e){
        setTimeout(()=>{
          const val = input.value.trim();
          if (val) submitValue(val);
        }, 50);
      });

      // Keep page minimal: prevent double clicks
      document.addEventListener('touchstart', ()=>{}, {passive:true});
    })();
  </script>
</body>
</html>
