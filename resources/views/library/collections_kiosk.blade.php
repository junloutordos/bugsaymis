<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>OPAC</title>
  <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
  <style>
    html,body{height:100%;margin:0;font-family:Inter,system-ui,Arial,sans-serif;background:#0f172a url('/images/bg.jpg') no-repeat center center fixed;background-size:cover;color:#e6eef8}
    .wrap{min-height:100%;display:flex;align-items:flex-start;justify-content:center;padding:40px}
    .card{background:rgba(255,255,255,0.03);padding:20px;border-radius:12px;min-width:320px;max-width:980px}
    .title{font-size:22px;margin-bottom:6px}
    .sub{font-size:13px;color:#94a3b8;margin-bottom:12px}
    input.search{font-size:18px;padding:12px 14px;border-radius:8px;border:0;outline:none;width:100%;box-sizing:border-box;background:#071233;color:#e6eef8}
    .results{margin-top:16px}
    .item{padding:12px;border-radius:8px;background:rgba(255,255,255,0.02);margin-bottom:8px}
    .muted{color:#94a3b8;font-size:13px}
    .status-available{color:#10b981;font-weight:600}
    .status-borrowed{color:#ef4444;font-weight:600}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div>
        <img src="/images/report_header.jpeg" alt="Library Header" style="width:100%;border-radius:8px;margin-bottom:12px;object-fit:cover;max-height:140px;">
      </div>
      <div class="title">Online Public Access Catalog (OPAC)</div>
      <div class="sub">Search books and view availability</div>
      <div style="display:flex;gap:12px;align-items:center">
        <input id="q" class="search" placeholder="Search collections..." autocomplete="off" style="flex:1" />
        <select id="type" style="min-width:160px;padding:10px;border-radius:8px;background:#071233;color:#e6eef8;border:1px solid rgba(255,255,255,0.06)">
          <option value="">All Types</option>
        </select>
        <select id="category" style="min-width:160px;padding:10px;border-radius:8px;background:#071233;color:#e6eef8;border:1px solid rgba(255,255,255,0.06)">
          <option value="">All Categories</option>
        </select>
      </div>
      <div id="message" class="muted" style="margin-top:8px">Type to search. Results update automatically.</div>
      <div id="results" class="results" style="margin-top:12px">
        <div style="overflow:auto;border-radius:8px;background:#ffffff;color:#0f172a;box-shadow:0 1px 4px rgba(2,6,23,0.12);">
          <table id="results-table" style="width:100%;border-collapse:collapse;min-width:720px;color:#0f172a">
            <thead style="background:#f3f4f6;color:#111827;">
              <tr>
                <th style="text-align:left;padding:12px;border-bottom:1px solid #e6e9ef;width:60px">Accession Number</th>
                <th style="text-align:left;padding:12px;border-bottom:1px solid #e6e9ef">Title</th>
                <th style="text-align:left;padding:12px;border-bottom:1px solid #e6e9ef;width:180px">Author / Publisher</th>
                <th style="text-align:left;padding:12px;border-bottom:1px solid #e6e9ef;width:120px">Call #</th>
                <th style="text-align:left;padding:12px;border-bottom:1px solid #e6e9ef;width:140px">Category</th>
                <th style="text-align:left;padding:12px;border-bottom:1px solid #e6e9ef;width:120px">Status</th>
              </tr>
            </thead>
            <tbody id="results-body"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

      <script>
    (function(){
      const qel = document.getElementById('q');
      const resultsBody = document.getElementById('results-body');
      const msg = document.getElementById('message');
      const typeSel = document.getElementById('type');
      const catSel = document.getElementById('category');
      let timer = null;
      let latestData = [];

      function escapeHtml(s){ if (!s) return ''; return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c]; }); }

      function renderTable(items){
        resultsBody.innerHTML = '';
        if (!items || items.length === 0) {
          resultsBody.innerHTML = '<tr><td colspan="6" style="padding:12px" class="muted">No results</td></tr>';
          return;
        }
        for (const it of items) {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td style="padding:12px;border-top:1px solid rgba(255,255,255,0.03)">${escapeHtml(it.id)}</td>
            <td style="padding:12px;border-top:1px solid rgba(255,255,255,0.03)"><div style="font-weight:600">${escapeHtml(it.title || 'Untitled')}</div><div class="muted" style="margin-top:4px">${escapeHtml(it.edition || '')}</div></td>
            <td style="padding:12px;border-top:1px solid rgba(255,255,255,0.03)">${escapeHtml(it.author_publisher || '')}</td>
            <td style="padding:12px;border-top:1px solid rgba(255,255,255,0.03)">${escapeHtml(it.call_number || '')}</td>
            <td style="padding:12px;border-top:1px solid rgba(255,255,255,0.03)">${escapeHtml(it.category || it.collection_type || '')}</td>
            <td style="padding:12px;border-top:1px solid rgba(255,255,255,0.03)">${it.is_borrowed ? '<span class="status-borrowed">Borrowed</span>' : '<span class="status-available">Available</span>'}</td>
          `;
          resultsBody.appendChild(tr);
        }
      }

      function populateFilters(items){
        const types = new Set();
        const cats = new Set();
        items.forEach(i => { if (i.collection_type) types.add(i.collection_type); if (i.category) cats.add(i.category); });
        // preferred type order / labels
        const preferred = ['Instructional Materials','Book','Periodical','Non-Print','Thesis'];
        // clear
        typeSel.innerHTML = '<option value="">All Types</option>';
        // add preferred options first (even if not present in results)
        preferred.forEach(t => { const o = document.createElement('option'); o.value = t; o.textContent = t; typeSel.appendChild(o); });
        // append any additional types discovered from results
        Array.from(types).sort().forEach(t => { if (!preferred.includes(t)) { const o = document.createElement('option'); o.value = t; o.textContent = t; typeSel.appendChild(o); } });

        // Categories: include preferred ones first
        const preferredCats = ['RESEARCH','THESIS'];
        catSel.innerHTML = '<option value="">All Categories</option>';
        preferredCats.forEach(c => { const o = document.createElement('option'); o.value = c; o.textContent = c; catSel.appendChild(o); });
        Array.from(cats).sort().forEach(c => { if (!preferredCats.includes(c)) { const o = document.createElement('option'); o.value = c; o.textContent = c; catSel.appendChild(o); } });
      }

      function applyClientFilters(){
        const t = typeSel.value;
        const c = catSel.value;
        const q = qel.value.trim().toLowerCase();
        let out = latestData.filter(it => {
          if (t && it.collection_type !== t) return false;
          if (c && (it.category || '') !== c) return false;
          if (q) {
            const hay = ((it.title||'') + ' ' + (it.author_publisher||'') + ' ' + (it.call_number||'') + ' ' + (it.isbn||'')).toLowerCase();
            if (!hay.includes(q)) return false;
          }
          return true;
        });
        renderTable(out);
        msg.textContent = `Found ${out.length} result${out.length === 1 ? '' : 's'}`;
      }

      async function fetchSearch(q){
        msg.textContent = 'Searching...';
        try {
          const res = await fetch('/library/collections/kiosk/search?q='+encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
          if (!res.ok) throw new Error('Network error');
          const data = await res.json();
          latestData = Array.isArray(data) ? data : (data.data || []);
          populateFilters(latestData);
          applyClientFilters();
        } catch (e) {
          msg.textContent = 'Search failed';
          resultsBody.innerHTML = '<tr><td colspan="6" style="padding:12px" class="muted">Unable to fetch results</td></tr>';
        }
      }

      qel.addEventListener('input', function(e){
        if (timer) clearTimeout(timer);
        timer = setTimeout(()=>{ fetchSearch(this.value.trim()); }, 250);
      });
      typeSel.addEventListener('change', applyClientFilters);
      catSel.addEventListener('change', applyClientFilters);

      window.addEventListener('load', ()=>{ fetchSearch(''); qel.focus(); });
    })();
  </script>
</body>
</html>
