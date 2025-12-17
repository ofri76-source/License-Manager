/* KB Billing Manager - ES5 Admin Script (no arrow/template/spread/let/const) */
(function($){
  'use strict';

  function logDebug(){
    try{
      if (window.console && console.log) console.log.apply(console, arguments);
    }catch(e){}
  }

  function parseTenantText(text){
    text = (text || '').replace(/\r/g, '');
    function pick(re){
      var m = re.exec(text);
      return (m && m[1]) ? ('' + m[1]).trim() : '';
    }
    var tenantId = pick(/Tenant\s*ID\s*:\s*([0-9a-fA-F-]{36})/i);
    var clientId = pick(/Application\s*\(Client\)\s*ID\s*:\s*([0-9a-fA-F-]{36})/i) || pick(/Client\s*ID\s*:\s*([0-9a-fA-F-]{36})/i);
    var clientSecret = pick(/Client\s*Secret\s*:\s*([^\n]+)/i);
    if (clientSecret) clientSecret = clientSecret.replace(/\s+/g, '').trim();
    var tenantDomain = pick(/Tenant\s*Domain\s*:\s*([^\n]+)/i);
    return { tenantId: tenantId, clientId: clientId, clientSecret: clientSecret, tenantDomain: tenantDomain };
  }

  function serializeTenants(){
    var hid = document.getElementById('customer-tenants-json');
    if (!hid) return;

    var container = document.getElementById('additional-tenants');
    if (!container) { hid.value = '[]'; return; }

    var cards = container.querySelectorAll('.kbbm-tenant-card');
    var out = [];
    for (var i=0;i<cards.length;i++){
      var c = cards[i];
      var t = {
        tenant_id: (c.querySelector('.kbbm-tenant-id') || {}).value || '',
        tenant_domain: (c.querySelector('.kbbm-tenant-domain') || {}).value || '',
        client_id: (c.querySelector('.kbbm-tenant-client-id') || {}).value || '',
        client_secret: (c.querySelector('.kbbm-tenant-client-secret') || {}).value || ''
      };
      var any = false, k;
      for (k in t){ if (t.hasOwnProperty(k) && (t[k]||'').trim() !== '') any=true; }
      if (any) out.push(t);
    }
    hid.value = JSON.stringify(out);
  }

  function bindInputsToSerialize(root){
    var els = root.querySelectorAll('input,textarea');
    for (var i=0;i<els.length;i++){
      els[i].addEventListener('input', serializeTenants);
      els[i].addEventListener('change', serializeTenants);
    }
  }

  function addTenantCard(){
    var container = document.getElementById('additional-tenants');
    if (!container) return;

    var idx = container.querySelectorAll('.kbbm-tenant-card').length + 1;
    var card = document.createElement('div');
    card.className = 'kbbm-tenant-card';

    card.innerHTML =
      '<div class="kbbm-tenant-card-header">' +
        '<h4>טננט נוסף #' + idx + '</h4>' +
        '<button type="button" class="m365-btn m365-btn-small kbbm-tenant-remove">הסר</button>' +
      '</div>' +
      '<div class="kb-fortis-field kbbm-tenant-paste">' +
        '<label>הדבקת פרטי טננט</label>' +
        '<textarea class="kbbm-tenant-paste-src" rows="4" placeholder="הדבק כאן Tenant ID / Application (Client) ID / Client Secret ..."></textarea>' +
        '<div class="kbbm-tenant-actions" style="margin-top:8px;">' +
          '<button type="button" class="m365-btn m365-btn-small kbbm-tenant-paste-fill">מלא שדות מהטקסט</button>' +
        '</div>' +
      '</div>' +
      '<div class="kbbm-tenant-grid">' +
        '<div class="kb-fortis-field"><label>Tenant ID</label><input type="text" class="kbbm-tenant-id" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"></div>' +
        '<div class="kb-fortis-field"><label>Tenant Domain</label><input type="text" class="kbbm-tenant-domain" placeholder="example.onmicrosoft.com"></div>' +
        '<div class="kb-fortis-field"><label>Client ID</label><input type="text" class="kbbm-tenant-client-id" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"></div>' +
        '<div class="kb-fortis-field"><label>Client Secret</label><input type="text" class="kbbm-tenant-client-secret" placeholder=""></div>' +
      '</div>';

    var rm = card.querySelector('.kbbm-tenant-remove');
    rm.addEventListener('click', function(){
      card.parentNode.removeChild(card);
      serializeTenants();
    });

    var fill = card.querySelector('.kbbm-tenant-paste-fill');
    fill.addEventListener('click', function(){
      var txt = (card.querySelector('.kbbm-tenant-paste-src') || {}).value || '';
      var p = parseTenantText(txt);
      if (p.tenantId) card.querySelector('.kbbm-tenant-id').value = p.tenantId;
      if (p.tenantDomain) card.querySelector('.kbbm-tenant-domain').value = p.tenantDomain;
      if (p.clientId) card.querySelector('.kbbm-tenant-client-id').value = p.clientId;
      if (p.clientSecret) card.querySelector('.kbbm-tenant-client-secret').value = p.clientSecret;
      serializeTenants();
    });

    bindInputsToSerialize(card);
    container.appendChild(card);
    serializeTenants();
  }

  function initTenantUI(){
    var btn = document.getElementById('add-tenant-row');
    if (!btn || btn.__kbbmBound) return;
    btn.__kbbmBound = true;
    btn.addEventListener('click', function(e){
      e.preventDefault();
      addTenantCard();
    });
  }

  function initMainPaste(){
    var src = document.getElementById('customer-paste-source');
    var btn = document.getElementById('customer-paste-fill');
    if (!src || !btn || btn.__kbbmBound) return;
    btn.__kbbmBound = true;

    btn.addEventListener('click', function(e){
      e.preventDefault();
      var p = parseTenantText(src.value || '');
      if (p.tenantId) { var el=document.getElementById('customer-tenant-id'); if (el) el.value=p.tenantId; }
      if (p.clientId) { var el2=document.getElementById('customer-client-id'); if (el2) el2.value=p.clientId; }
      if (p.clientSecret) { var el3=document.getElementById('customer-client-secret'); if (el3) el3.value=p.clientSecret; }
      if (p.tenantDomain) { var el4=document.getElementById('customer-tenant-domain'); if (el4) el4.value=p.tenantDomain; }
    });
  }

  function initCustomerLookup(){
    var input = document.getElementById('customer-lookup');
    var results = document.getElementById('customer-lookup-results');
    if (!input || !results || input.__kbbmBound) return;
    input.__kbbmBound = true;

    function clear(){
      results.innerHTML = '';
      results.style.display = 'none';
    }

    function render(list){
      results.innerHTML = '';
      if (!list || !list.length){ clear(); return; }
      for (var i=0;i<list.length;i++){
        (function(item){
          var div = document.createElement('div');
          div.className = 'customer-lookup-item';
          div.textContent = item.customer_number + ' - ' + item.customer_name;
          div.addEventListener('click', function(){
            var n = document.getElementById('customer-number');
            var nm = document.getElementById('customer-name');
            if (n) n.value = item.customer_number;
            if (nm) nm.value = item.customer_name;
            clear();
          });
          results.appendChild(div);
        })(list[i]);
      }
      results.style.display = 'block';
    }

    input.addEventListener('input', function(){
      var q = (input.value || '').toLowerCase().trim();
      if (!q){ clear(); return; }

      var all = (window.m365Ajax && window.m365Ajax.dcCustomers) ? window.m365Ajax.dcCustomers : [];
      var out = [];
      for (var i=0;i<all.length;i++){
        var it = all[i];
        var cn = (it.customer_number || '').toLowerCase();
        var nm = (it.customer_name || '').toLowerCase();
        if (cn.indexOf(q) !== -1 || nm.indexOf(q) !== -1){
          out.push(it);
          if (out.length >= 30) break;
        }
      }
      render(out);
    });

    document.addEventListener('click', function(e){
      if (!results.contains(e.target) && e.target !== input) clear();
    });
  }

  function initCustomerFormAjax(){
    var form = document.getElementById('customer-form');
    if (!form || form.__kbbmBound) return;
    form.__kbbmBound = true;

    $(form).on('submit', function(ev){
      ev.preventDefault();
      if (!window.m365Ajax || !window.m365Ajax.ajaxUrl) return;

      serializeTenants();

      var data = $(form).serializeArray();
      data.push({name:'action', value:'m365_save_customer'});
      if (window.m365Ajax.nonce) data.push({name:'nonce', value: window.m365Ajax.nonce});

      $.post(window.m365Ajax.ajaxUrl, data)
        .done(function(resp){
          // simplest: reload to refresh list + status
          try{ logDebug('save_customer resp', resp); }catch(e){}
          window.location.reload();
        })
        .fail(function(xhr){
          alert('שגיאה בשמירה: ' + (xhr && xhr.status ? xhr.status : ''));
        });
    });
  }

  function boot(){
    initTenantUI();
    initMainPaste();
    initCustomerLookup();
    initCustomerFormAjax();
  }

  $(document).ready(function(){
    boot();
    setTimeout(boot, 500);
    setTimeout(boot, 1500);
  });

})(jQuery);
