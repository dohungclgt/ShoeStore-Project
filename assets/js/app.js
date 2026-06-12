$(function(){
  const savedTheme = localStorage.getItem('shoestore_theme') || 'light';
  document.documentElement.setAttribute('data-theme', savedTheme);
  $('.theme-toggle').on('click', function(){
    const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('shoestore_theme', next);
  });

  $('.datatable').each(function(){
    const headerCount = $(this).find('thead tr:first th').length;
    const valid = headerCount > 0 && $(this).find('tbody tr').toArray().every(row => $(row).children('td,th').length === headerCount);
    if (valid) new DataTable(this, {language:{url:'https://cdn.datatables.net/plug-ins/2.0.8/i18n/vi.json'}});
  });

  $('[data-confirm]').on('click', function(e){
    e.preventDefault();
    const href = this.href;
    Swal.fire({title:'Xác nhận thao tác',text:$(this).data('confirm'),icon:'warning',showCancelButton:true,confirmButtonText:'Xác nhận',cancelButtonText:'Hủy'}).then(r=>{if(r.isConfirmed) location.href=href;});
  });

  $('form[data-confirm-submit]').on('submit', function(e){
    if (this.dataset.confirmed === '1') return;
    e.preventDefault();
    const form = this;
    Swal.fire({title:'Xác nhận lưu dữ liệu',text:$(form).data('confirm-submit') || 'Bạn có chắc chắn muốn thực hiện thao tác này?',icon:'question',showCancelButton:true,confirmButtonText:'Xác nhận',cancelButtonText:'Hủy'}).then(r=>{if(r.isConfirmed){form.dataset.confirmed='1';form.submit();}});
  });

  $('.fade-target').each(function(i){ $(this).css('animation-delay', `${i*70}ms`).addClass('fade-up'); });
  $('.skeleton').delay(450).fadeOut(180);

  const panel = $('#chatbot-panel');
  const savedChatPos = JSON.parse(localStorage.getItem('shoestore_chatbot_pos') || 'null');
  if (savedChatPos && window.innerWidth > 576) panel.css({left:savedChatPos.left, top:savedChatPos.top, right:'auto', bottom:'auto'});
  $('#chatbot-toggle').on('click',()=>panel.toggleClass('d-none').removeClass('minimized'));
  $('#chatbot-close').on('click',()=>panel.addClass('d-none'));
  $('#chatbot-minimize').on('click',()=>panel.toggleClass('minimized'));
  let dragging=false, offset={x:0,y:0};
  $('#chatbot-drag-handle').on('mousedown touchstart', function(e){
    const ev=e.type==='touchstart'?e.originalEvent.touches[0]:e;
    dragging=true;
    const pos=panel.offset();
    offset={x:ev.pageX-pos.left,y:ev.pageY-pos.top};
    e.preventDefault();
  });
  $(document).on('mousemove touchmove', function(e){
    if(!dragging) return;
    const ev=e.type==='touchmove'?e.originalEvent.touches[0]:e;
    const w=panel.outerWidth(), h=panel.outerHeight();
    const left=Math.max(8, Math.min(window.innerWidth-w-8, ev.pageX-offset.x));
    const top=Math.max(70, Math.min(window.innerHeight-h-8, ev.pageY-offset.y));
    panel.css({left,top,right:'auto',bottom:'auto'});
  }).on('mouseup touchend', function(){
    if(dragging){
      dragging=false;
      localStorage.setItem('shoestore_chatbot_pos', JSON.stringify({left:panel.css('left'), top:panel.css('top')}));
    }
  });

  $('#chatbot-form').on('submit', function(e){
    e.preventDefault();
    const input=$(this).find('[name=message]');
    const message=input.val().trim();
    if(!message) return;
    $('#chatbot-log').append(`<div class="bubble user">${$('<div>').text(message).html()}</div>`);
    input.val('');
    function renderChatProducts(products){
      if(!products || !products.length) return '';
      return `<div class="chat-product-grid">${products.map(p=>`
        <div class="chat-product-card">
          <img src="${$('<div>').text(p.image || '').html()}" alt="${$('<div>').text(p.name || '').html()}">
          <div class="chat-product-body">
            <strong>${$('<div>').text(p.name || '').html()}</strong>
            <span>${$('<div>').text(p.brand || 'ShoeStore').html()} · ★ ${$('<div>').text(p.rating || '4.8').html()}</span>
            <div class="chat-product-price">${$('<div>').text(p.price_label || '').html()}</div>
            <small>Còn ${Number(p.stock || 0).toLocaleString('vi-VN')} sản phẩm</small>
            <a class="btn btn-sm btn-dark mt-2" href="${$('<div>').text(p.url || '#').html()}">Xem chi tiết</a>
          </div>
        </div>`).join('')}</div>`;
    }
    $.ajax({url: window.SHOESTORE.base + 'api/chatbot.php', method:'POST', data:{message, csrf_token:window.SHOESTORE.csrf}})
      .done(res=>$('#chatbot-log').append(`<div class="bubble bot">${$('<div>').text(res.reply).html()}${renderChatProducts(res.products)}</div>`))
      .fail(xhr=>$('#chatbot-log').append(`<div class="bubble bot">${xhr.responseJSON?.error || 'Vui lòng đăng nhập để dùng chatbot.'}</div>`));
  });

  $(document).on('click', '.popup-click', function(){ fetch(window.SHOESTORE.base + 'api/popup.php?action=click&id=' + this.dataset.id).catch(()=>{}); });
  $(document).on('click', '.review-lightbox', function(e){
    e.preventDefault();
    Swal.fire({imageUrl:this.href,imageAlt:'Ảnh đánh giá',showConfirmButton:false,showCloseButton:true,width:'min(920px,96vw)',background:'var(--surface)'});
  });

  function pollNotifications(){
    $.getJSON(window.SHOESTORE.base + 'api/notifications/poll.php').done(res=>{
      $('#notificationBadge').text(res.count).toggle(res.count>0);
      if(res.items){
        const html = res.items.length ? res.items.map(n=>`<div class="dropdown-item notification-item ${Number(n.is_read)===0?'unread':''}"><strong>${$('<div>').text(n.title).html()}</strong><p class="mb-0 small text-muted">${$('<div>').text(n.body||'').html()}</p></div>`).join('') : '<div class="dropdown-item text-muted">Chưa có thông báo.</div>';
        $('#notificationMenu').html(html);
      }
      if(res.count>0) document.title='('+res.count+') ShoeStore';
    });
  }
  setInterval(pollNotifications, 4000);
  pollNotifications();
  $('#notificationBell').on('shown.bs.dropdown', function(){
    pollNotificationsRich();
  });

  function renderNotificationMenu(items, count){
    $('#notificationBadge').text(count).toggle(count>0);
    const notificationHref = link => {
      const fallback = $('body').hasClass('admin-shell') ? 'admin/notifications.php' : 'user/notifications.php';
      link = String(link || fallback);
      if (/^https?:\/\//i.test(link)) return link;
      return window.SHOESTORE.base + link.replace(/^\/+/, '');
    };
    const html = `<div class="notification-menu-head"><strong>Thông báo</strong><button class="btn btn-link btn-sm p-0" type="button" id="notificationReadAll">Đánh dấu tất cả là đã đọc</button></div>` +
      (items && items.length ? items.map(n=>`<div class="dropdown-item notification-item ${Number(n.is_read)===0?'unread':''}" data-id="${Number(n.id)}">
        <span class="notification-icon"><i class="fa-solid ${$('<div>').text(n.icon || 'fa-circle-info').html()}"></i></span>
        <span class="notification-copy"><strong>${$('<div>').text(n.title || '').html()}</strong><p class="mb-1 small text-muted">${$('<div>').text(n.message || n.body || '').html()}</p><small>${$('<div>').text(n.created_at || '').html()}</small><a class="btn btn-sm btn-outline-dark mt-2 notification-detail-link" data-id="${Number(n.id)}" href="${$('<div>').text(notificationHref(n.link)).html()}">Xem chi tiết</a></span>
      </div>`).join('') : '<div class="dropdown-item text-muted">Chưa có thông báo.</div>') +
      `<div class="notification-menu-foot"><a href="${window.SHOESTORE.base}${$('body').hasClass('admin-shell')?'admin/notifications.php':'user/notifications.php'}">Xem tất cả</a></div>`;
    $('#notificationMenu').html(html);
  }
  function pollNotificationsRich(){
    $.getJSON(window.SHOESTORE.base + 'api/notifications/poll.php').done(res=>renderNotificationMenu(res.items || [], Number(res.count || 0)));
  }
  setInterval(pollNotificationsRich, 4000);
  pollNotificationsRich();
  $(document).on('click', '#notificationReadAll,#notificationPageReadAll', function(){
    $.post(window.SHOESTORE.base + 'api/notifications/read-all.php', {csrf_token:window.SHOESTORE.csrf}).done(()=>{pollNotificationsRich(); if(location.pathname.endsWith('/notifications.php')) location.reload();});
  });
  $(document).on('click', '.notification-detail-link', function(e){
    const id = $(this).data('id');
    if(id) {
      e.preventDefault();
      const href = this.href;
      $.post(window.SHOESTORE.base + 'api/notifications/read.php', {csrf_token:window.SHOESTORE.csrf, id}).always(()=>{ window.location.href = href; });
    }
  });

  let activeTicketId = Number($('.ticket-page,.ticket-admin-page').data('initial-ticket') || 0);
  function renderTicketList(items){
    const target = $('#ticketList');
    if(!target.length) return;
    target.html(items.length ? items.map(t=>`<button type="button" class="ticket-list-item ${Number(t.id)===activeTicketId?'active':''}" data-id="${Number(t.id)}">
      <span><strong>${$('<div>').text(t.subject).html()}</strong><small>${$('<div>').text(t.customer || '').html()} · ${$('<div>').text(t.updated_at || t.created_at).html()}</small></span>
      <span class="badge bg-${t.status==='closed'?'secondary':(Number(t.unread)>0?'danger':'dark')}">${$('<div>').text(t.status_label).html()}${Number(t.unread)>0?' · '+Number(t.unread):''}</span>
    </button>`).join('') : '<div class="text-muted">Chưa có ticket.</div>');
  }
  function loadTickets(){
    if(!$('#ticketList').length) return;
    const params = {};
    if($('#ticketFilterStatus').length) params.status = $('#ticketFilterStatus').val();
    if($('#ticketSearch').length) params.q = $('#ticketSearch').val();
    $.getJSON(window.SHOESTORE.base + 'api/tickets/list.php', params).done(res=>{
      renderTicketList(res.items || []);
      if(activeTicketId) loadTicketDetail(activeTicketId, false);
    });
  }
  function loadTicketDetail(id, refreshList=true){
    if(!id) return;
    $.getJSON(window.SHOESTORE.base + 'api/tickets/detail.php', {id}).done(res=>{
      activeTicketId = id;
      $('#ticketDetailEmpty').addClass('d-none');
      $('#ticketDetail').removeClass('d-none');
      $('#ticketTitle').text(res.ticket.subject);
      $('#ticketStatus').text(res.ticket.status_label);
      $('#ticketCustomer').text(res.ticket.customer ? `${res.ticket.customer} · ${res.ticket.email}` : '');
      $('#ticketReplyId').val(id);
      $('#ticketStatusSelect').val(res.ticket.status);
      if (!$('body').hasClass('admin-shell') && res.ticket.status === 'closed') {
        $('#ticketReplyForm').addClass('d-none');
        if(!$('#ticketClosedNotice').length) $('#ticketMessages').after('<div id="ticketClosedNotice" class="alert alert-warning mt-3">Ticket này đã đóng. Nếu cần hỗ trợ thêm, vui lòng tạo ticket mới.</div>');
      } else {
        $('#ticketReplyForm').removeClass('d-none');
        $('#ticketClosedNotice').remove();
      }
      $('#ticketMessages').html((res.messages || []).map(m=>`<div class="ticket-message ${m.mine?'mine':''}">
        <div class="ticket-message-meta">${$('<div>').text(m.sender).html()} · ${$('<div>').text(m.created_at).html()}</div>
        <div>${$('<div>').text(m.message).html().replace(/\n/g,'<br>')}</div>
        ${m.attachment?`<a href="${$('<div>').text(m.attachment).html()}" target="_blank">Tệp đính kèm</a>`:''}
      </div>`).join(''));
      if(refreshList) loadTickets();
    });
  }
  $(document).on('click', '.ticket-list-item', function(){ loadTicketDetail(Number(this.dataset.id)); });
  $('#ticketCreateForm').on('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    $.ajax({url:window.SHOESTORE.base+'api/tickets/create.php', method:'POST', data:fd, processData:false, contentType:false})
      .done(res=>{ activeTicketId=Number(res.ticket_id); bootstrap.Modal.getInstance(document.getElementById('ticketModal'))?.hide(); this.reset(); loadTickets(); })
      .fail(xhr=>Swal.fire('Lỗi', xhr.responseJSON?.error || 'Không tạo được ticket.', 'error'));
  });
  $('#ticketReplyForm').on('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    $.ajax({url:window.SHOESTORE.base+'api/tickets/reply.php', method:'POST', data:fd, processData:false, contentType:false})
      .done(()=>{ this.reset(); $('#ticketReplyId').val(activeTicketId); loadTicketDetail(activeTicketId); })
      .fail(xhr=>Swal.fire('Lỗi', xhr.responseJSON?.error || 'Không gửi được phản hồi.', 'error'));
  });
  $('#ticketRefresh,#ticketFilterStatus').on('click change', loadTickets);
  $('#ticketSearch').on('input', function(){ clearTimeout(searchTimer); searchTimer=setTimeout(loadTickets, 350); });
  $('#ticketSaveStatus').on('click', function(){
    if(!activeTicketId) return;
    $.post(window.SHOESTORE.base+'api/tickets/status.php', {csrf_token:window.SHOESTORE.csrf, ticket_id:activeTicketId, status:$('#ticketStatusSelect').val(), assigned_to:$('#ticketAssignSelect').val()}).done(()=>loadTicketDetail(activeTicketId));
  });
  function pollTickets(){
    if(!$('#ticketList').length) return;
    $.getJSON(window.SHOESTORE.base+'api/tickets/poll.php').done(res=>{
      $('#ticketAdminBadge').text(res.unread).toggle(res.unread>0);
      loadTickets();
    });
  }
  if($('#ticketList').length){ loadTickets(); setInterval(pollTickets, 4000); }

  let searchTimer=null;
  function renderSearchItems(items){
    const target=$('#homeSearchResults');
    if(!target.length) return;
    if(!items.length){ target.html('<div class="alert alert-info">Không tìm thấy sản phẩm phù hợp.</div>'); return; }
    target.html(items.map(p=>`<div class="col-sm-6 col-lg-3 fade-target"><div class="card product-card"><img src="${p.image}" class="card-img-top" alt=""><div class="card-body d-flex flex-column"><span class="text-muted small">${p.category_name} · Còn ${p.stock}</span><h3 class="h6 mt-1">${$('<div>').text(p.name).html()}</h3><p class="price mb-3">${Number(p.sale_price || p.price).toLocaleString('vi-VN')} VND</p><a class="btn btn-dark mt-auto" href="${window.SHOESTORE.base}product.php?slug=${encodeURIComponent(p.slug)}">Chi tiết</a></div></div></div>`).join(''));
  }
  function collectHomeFilters(){ return $('#homeSearchForm').serialize(); }
  $('#homeSearchForm input, #homeSearchForm select').on('input change', function(){
    clearTimeout(searchTimer);
    $('#homeSearchLoading').removeClass('d-none');
    searchTimer=setTimeout(()=>{$.getJSON(window.SHOESTORE.base+'api/search.php', collectHomeFilters()).done(res=>renderSearchItems(res.items||[])).always(()=>$('#homeSearchLoading').addClass('d-none'));},300);
  });
  $('#saveHomeFilters').on('click',()=>{localStorage.setItem('shoestore_home_filters', collectHomeFilters()); Swal.fire('Đã lưu','Bộ lọc đã được lưu trên trình duyệt.','success');});
  $('#resetHomeFilters').on('click',()=>{localStorage.removeItem('shoestore_home_filters'); $('#homeSearchForm')[0]?.reset(); $('#homeSearchForm input:first').trigger('input');});
  const savedFilters=localStorage.getItem('shoestore_home_filters');
  if(savedFilters && $('#homeSearchForm').length){
    new URLSearchParams(savedFilters).forEach((v,k)=>$(`#homeSearchForm [name="${k}"]`).val(v));
    $('#homeSearchForm input:first').trigger('input');
  }
});
