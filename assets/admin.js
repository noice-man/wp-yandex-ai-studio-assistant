(function () {
  'use strict';
  var conf = window.WPRYAI || {};
  var form = document.getElementById('wpr-yai-form');
  var status = document.getElementById('wpr-yai-status');
  var output = document.getElementById('wpr-yai-output');
  var spinner = document.getElementById('wpr-yai-spinner');
  var history = [];
  function node(tag, className, text) {
    var n = document.createElement(tag);
    if (className) n.className = className;
    if (text !== undefined) n.textContent = String(text);
    return n;
  }
  function statusText(message, error) { if (status) { status.textContent = message; status.className = error ? 'wpr-yai-error' : ''; } }
  function request(action, data) {
    var body = new URLSearchParams({action: action, nonce: conf.nonce});
    Object.keys(data || {}).forEach(function (key) {body.append(key, String(data[key]));});
    return fetch(conf.ajax, {method: 'POST', body: body, credentials: 'same-origin'})
      .then(function (res) { return res.json().catch(function () {throw new Error('Сервер вернул не-JSON ответ. Проверьте PHP error.log.');}); })
      .then(function (json) { if (!json.success) throw new Error((json.data && json.data.message) || 'Не удалось выполнить запрос.'); return json.data; });
  }
  function appendField(label, value, pre) {
    var group = node('div', 'wpr-yai-field');
    group.appendChild(node('strong', '', label));
    group.appendChild(node(pre ? 'pre' : 'div', pre ? 'wpr-yai-code' : '', value));
    output.appendChild(group);
  }
  if (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var mode = document.getElementById('wpr-yai-mode').value;
      var prompt = document.getElementById('wpr-yai-prompt').value.trim();
      var postId = document.getElementById('wpr-yai-target').value;
      var postType = document.getElementById('wpr-yai-post-type').value;
      var search = document.getElementById('wpr-yai-search').value;
      if (!document.getElementById('wpr-yai-consent').checked) {statusText('Подтвердите передачу данных в Yandex AI Studio.', true); return;}
      if (mode === 'rewrite' && !postId) {statusText('Выберите существующую запись для редактирования.', true); return;}
      var button = form.querySelector('button[type=submit]');
      button.disabled = true; spinner.classList.add('is-active');
      output.replaceChildren(); statusText('Обрабатываем запрос. Это может занять до минуты…');
      request('wpr_yai_task', {mode: mode, prompt: prompt, post_id: postId, post_type: postType, search: search, history: mode === 'chat' ? JSON.stringify(history.slice(-6)) : '[]'})
        .then(function (data) {
          statusText('Результат получен.');
          if (data.kind === 'text') {
            output.appendChild(node('pre', 'wpr-yai-code', data.text));
            if (mode === 'chat') {history.push({role: 'user', content: prompt}, {role: 'assistant', content: data.text}); history = history.slice(-6);}
            else history = [];
            return;
          }
          history = [];
          var draft = data.draft;
          appendField('Тип записи (CPT)', draft.post_type || postType);
          appendField('Заголовок', draft.title);
          appendField('Slug', draft.slug);
          appendField('Анонс', draft.excerpt);
          appendField('Yoast SEO — Title', draft.seo_title);
          appendField('Yoast SEO — Meta Description', draft.meta_description);
          appendField('Yoast SEO — Focus Keyphrase', draft.focus_keyphrase);
          if (draft.acf_fields && Object.keys(draft.acf_fields).length) appendField('Поля ACF', JSON.stringify(draft.acf_fields, null, 2), true);
          if (draft.taxonomy_terms && Object.keys(draft.taxonomy_terms).length) appendField('Термины таксономий (ID)', JSON.stringify(draft.taxonomy_terms, null, 2), true);
          appendField('HTML-контент (проверить перед сохранением)', draft.content_html, true);
          var note = node('p', 'description', mode === 'rewrite' ? 'Если исходная запись опубликована или запланирована, будет создан отдельный черновик. Если это черновик, он обновится.' : 'Будет создан новый черновик. Публикация только вручную.');
          output.appendChild(note);
          var save = node('button', 'button button-primary', data.save_action);
          save.type = 'button'; output.appendChild(save);
          save.addEventListener('click', function () {
            if (!window.confirm('Сохранить предложенный контент в WordPress как черновик?')) return;
            save.disabled = true; statusText('Сохраняем черновик…');
            request('wpr_yai_save', {ticket: data.ticket}).then(function (result) {
              statusText(result.message);
              var link = node('a', 'button', 'Открыть запись в WordPress ↗');
              link.href = result.edit_url; link.target = '_blank'; link.rel = 'noopener noreferrer';
              output.appendChild(node('p', '', 'Запись #' + result.post_id + ' сохранена.'));
              output.appendChild(link); save.remove();
            }).catch(function (err) { statusText(err.message, true); save.disabled = false; });
          });
        })
        .catch(function (err) {statusText(err.message, true);})
        .finally(function () {button.disabled = false; spinner.classList.remove('is-active');});
    });
  }
  var test = document.getElementById('wpr-yai-test');
  if (test) test.addEventListener('click', function () {
    var feedback = document.getElementById('wpr-yai-test-status');
    test.disabled = true; feedback.textContent = 'Проверяем соединение…';
    request('wpr_yai_test', {}).then(function (d) {feedback.textContent = d.message;}).catch(function (e) {feedback.textContent = e.message;}).finally(function () {test.disabled = false;});
  });
})();
/* Local contract preview and model catalog tools. */
(function () {
 'use strict';
 var c=window.WPRYAI||{};
 function request2(action,data){var f=new URLSearchParams({action:action,nonce:c.nonce});Object.keys(data||{}).forEach(function(k){f.append(k,data[k]);});return fetch(c.ajax,{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){if(!j.success)throw Error(j.data&&j.data.message||'Ошибка сервера');return j.data;});}
 var type=document.getElementById('wpr-yai-post-type'),target=document.getElementById('wpr-yai-target');
 function syncTypes(){if(!type||!target)return;for(var i=1;i<target.options.length;i++){var opt=target.options[i];opt.disabled=opt.dataset.type!==type.value;opt.hidden=opt.disabled;}target.value='';var v=document.getElementById('wpr-yai-contract-view');if(v)v.hidden=true;}
 if(type){type.addEventListener('change',syncTypes);syncTypes();}
 var contract=document.getElementById('wpr-yai-contract-btn');
 if(contract)contract.addEventListener('click',function(){var v=document.getElementById('wpr-yai-contract-view');v.hidden=false;v.textContent='Загружаем контракт...';contract.disabled=true;request2('wpr_yai_contract',{post_type:type.value}).then(function(d){v.textContent=JSON.stringify(d.contract,null,2);}).catch(function(e){v.textContent=e.message;}).finally(function(){contract.disabled=false;});});
 var model=document.getElementById('wpr-yai-model');
 if(model){var custom=document.getElementById('wpr-yai-model-custom-wrap'),desc=document.getElementById('wpr-yai-model-description');function sync(){var o=model.options[model.selectedIndex];if(custom)custom.hidden=model.value!=='custom';if(desc)desc.textContent=o.dataset.description||'Введите ID доступной модели из каталога Яндекса.';}model.addEventListener('change',sync);sync();}
 var btn=document.getElementById('wpr-yai-list-models');
 if(btn)btn.addEventListener('click',function(){var status=document.getElementById('wpr-yai-models-status');btn.disabled=true;status.textContent='Запрашиваем список моделей...';request2('wpr_yai_models',{}).then(function(d){status.textContent=d.models.length?'Модели вашего каталога ('+d.models.length+'):\n'+d.models.join('\n'):'Текстовых моделей в ответе не найдено.';}).catch(function(e){status.textContent=e.message;}).finally(function(){btn.disabled=false;});});
})();
