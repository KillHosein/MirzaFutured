var Script = function () {



//    sidebar dropdown menu

    jQuery('#sidebar .sub-menu > a').click(function () {
        var last = jQuery('.sub-menu.open', $('#sidebar'));
        last.removeClass("open");
        jQuery('.arrow', last).removeClass("open");
        jQuery('.sub', last).slideUp(200);
        var sub = jQuery(this).next();
        var opening = !sub.is(":visible");
        if (!opening) {
            jQuery('.arrow', jQuery(this)).removeClass("open");
            jQuery(this).parent().removeClass("open");
            sub.slideUp(200);
        } else {
            jQuery('.arrow', jQuery(this)).addClass("open");
            jQuery(this).parent().addClass("open");
            sub.slideDown(200);
        }
        jQuery(this).attr('aria-expanded', opening ? 'true' : 'false');
        var o = ($(this).offset());
        diff = 200 - o.top;
        if(diff>0)
            $("#sidebar").scrollTo("-="+Math.abs(diff),500);
        else
            $("#sidebar").scrollTo("+="+Math.abs(diff),500);
    });

//    sidebar toggle


    $(function() {
        function responsiveView() {
            var wSize = $(window).width();
            if (wSize <= 768) {
                $('#container').addClass('sidebar-close');
                $('#sidebar > ul').hide();
            }

            if (wSize > 768) {
                $('#container').removeClass('sidebar-close');
                $('#sidebar > ul').show();
            }
        }
        $(window).on('load', responsiveView);
        $(window).on('resize', responsiveView);
    });

    $('.icon-reorder').click(function () {
        if ($('#sidebar > ul').is(":visible") === true) {
            $('#main-content').css({
                'margin-right': '0px'
            });
            $('#sidebar').css({
                'margin-right': '-220px'
            });
            $('#sidebar > ul').hide();
            $("#container").addClass("sidebar-closed");
        } else {
            $('#main-content').css({
                'margin-right': '220px'
            });
            $('#sidebar > ul').show();
            $('#sidebar').css({
                'margin-right': '0'
            });
            $("#container").removeClass("sidebar-closed");
        }
    });

// custom scrollbar
    $("#sidebar").niceScroll({styler:"fb",cursorcolor:"#e8403f", cursorwidth: '3', cursorborderradius: '10px', background: '#404040', cursorborder: ''});

    $("html").niceScroll({styler:"fb",cursorcolor:"#e8403f", cursorwidth: '6', cursorborderradius: '10px', background: '#404040', cursorborder: '', zindex: '1000'});

// widget tools

    jQuery('.widget .tools .icon-chevron-down').click(function () {
        var el = jQuery(this).parents(".widget").children(".widget-body");
        if (jQuery(this).hasClass("icon-chevron-down")) {
            jQuery(this).removeClass("icon-chevron-down").addClass("icon-chevron-up");
            el.slideUp(200);
        } else {
            jQuery(this).removeClass("icon-chevron-up").addClass("icon-chevron-down");
            el.slideDown(200);
        }
    });

    jQuery('.widget .tools .icon-remove').click(function () {
        jQuery(this).parents(".widget").parent().remove();
    });

//    tool tips

    $('.tooltips').tooltip();

//    popovers

    $('.popovers').popover();



// custom bar chart

    if ($(".custom-bar-chart")) {
        $(".bar").each(function () {
            var i = $(this).find(".value").html();
            $(this).find(".value").html("");
            $(this).find(".value").animate({
                height: i
            }, 2000)
        })
    }


//custom select box

//    $(function(){
//
//        $('select.styled').customSelect();
//
//    });



// theme toggle (light/dark)
    (function(){
        var key = 'theme';
        function applyTheme(t){
            document.body.classList.toggle('dark', t === 'dark');
        }
        var saved = null;
        try{ saved = localStorage.getItem(key); }catch(e){}
        var initial = saved || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        applyTheme(initial);
        $('#themeToggle').closest('li').show();
        $('#themeToggle').attr('aria-pressed', initial==='dark' ? 'true' : 'false');
        $('#themeToggle').on('click', function(e){
            e.preventDefault();
            var now = document.body.classList.toggle('dark');
            var v = now ? 'dark' : 'light';
            $(this).attr('aria-pressed', now ? 'true' : 'false');
            try{ localStorage.setItem(key, v); }catch(ex){}
            if(window.showToast) showToast(now ? 'حالت تیره فعال شد' : 'حالت روشن فعال شد');
        });
    })();

    // global search in sidebar
    (function(){
        var $input = $('#globalSearch');
        if(!$input.length) return;
        var $items = $('#sidebar ul.sidebar-menu > li');
        function debounce(fn, wait){ var t; return function(){ var ctx=this, args=arguments; clearTimeout(t); t=setTimeout(function(){ fn.apply(ctx,args); }, wait); }; }
        $input.on('keyup', debounce(function(){
            var q = $(this).val().trim();
            if(q === ''){
                $items.show();
                return;
            }
            var regex = new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i');
            $items.each(function(){
                var $li = $(this);
                var text = $li.text();
                if(regex.test(text)){
                    $li.show();
                    // expand sub-menu if matched
                    if($li.hasClass('sub-menu')){
                        $li.addClass('open');
                        $li.find('.sub').slideDown(150);
                        $li.find('> a').attr('aria-expanded','true');
                    }
                } else {
                    $li.hide();
                }
            });
        },120));
        // shortcut: press / to focus
        $(document).on('keydown', function(e){
            if(e.key === '/' && !$(e.target).is('input, textarea')){
                e.preventDefault();
                $input.focus();
            }
        });
    })();

    // confirm delete dialogs (Persian)
    (function(){
        $(document).on('click','a[data-confirm]',function(e){
            var msg = $(this).attr('data-confirm') || 'آیا مطمئن هستید؟';
            if(!confirm(msg)){
                e.preventDefault();
                return false;
            }
        });
    })();

    // floating actions toggle
    (function(){
        var $fab = $('#fabToggle'); var $menu = $('#fabMenu');
        if(!$fab.length || !$menu.length) return;
        $fab.on('click', function(){ $menu.toggle(); });
        $(document).on('keydown', function(e){ if(e.shiftKey && e.key.toLowerCase()==='a'){ $menu.toggle(); } });
    })();

    // notifications polling
    (function(){
        var $badge = $('#notifBadge'); var $list = $('#notifList'); var last = {users:0, orders:0, payments:0}; var unseen=0;
        function render(items){ $list.empty(); items.forEach(function(it){ var $d = $('<div class="notif-item"></div>').css({padding:'8px 12px', borderBottom:'1px solid #eee'});
            var txt = it.type==='order' ? ('سفارش '+it.id+' ('+it.username+') - '+it.status) : ('پرداخت '+it.id+' از کاربر '+it.user+' - '+it.status);
            $d.text(txt); $list.append($d); }); }
        function tick(){ $.getJSON('metrics.php').done(function(r){ if(!r || !r.ok) return; var c=r.counts||{}; var items=[]; if(last.orders && c.orders>last.orders){ unseen += (c.orders-last.orders); (r.latest.orders||[]).forEach(function(o){ items.push({type:'order', id:o.id_invoice, username:o.username, status:o.Status}); }); }
            if(last.payments && c.payments>last.payments){ unseen += (c.payments-last.payments); (r.latest.payments||[]).forEach(function(p){ items.push({type:'payment', id:p.id_order, user:p.id_user, status:p.payment_Status}); }); }
            last = c; render(items); if(unseen>0){ $badge.text(unseen).show(); showToast('اعلان جدید'); } }); }
        $.getJSON('metrics.php').done(function(r){ if(r && r.ok){ last = r.counts||last; render([].concat((r.latest.orders||[]).map(function(o){ return {type:'order', id:o.id_invoice, username:o.username, status:o.Status}; }), (r.latest.payments||[]).map(function(p){ return {type:'payment', id:p.id_order, user:p.id_user, status:p.payment_Status}; }))); } });
        setInterval(tick, 10000);
        $('#notifDropdown > a').on('click', function(){ unseen=0; $badge.hide(); });
    })();

    // toast helper
    window.showToast = function(text){
        var $c = $('#toast-container'); if(!$c.length){ $c = $('<div id="toast-container"></div>').appendTo('body'); }
        var $t = $('<div class="toast"></div>').text(text).appendTo($c);
        setTimeout(function(){ $t.fadeOut(200, function(){ $(this).remove(); }); }, 2500);
    };

    // quick table search attach helper
    window.attachTableQuickSearch = function(tableSelector, inputSelector){
        var $table = $(tableSelector); var $inp = $(inputSelector);
        if(!$table.length || !$inp.length) return;
        $inp.on('keyup', function(){
            var q = $(this).val().toLowerCase();
            $table.find('tbody tr').each(function(){
                var txt = $(this).text().toLowerCase();
                $(this).toggle(txt.indexOf(q) !== -1);
            });
            if(window.updateBreadcrumb) window.updateBreadcrumb();
        });
    };

    window.showSkeleton = function(){
        var $o = $('#skeletonOverlay');
        if(!$o.length){
            $o = $('<div id="skeletonOverlay" class="skeleton-overlay"><div class="skeleton-content"><div class="skeleton-bar"></div><div class="skeleton-bar"></div><div class="skeleton-bar"></div></div></div>').appendTo('body');
        }
        $o.show();
    };
    window.hideSkeleton = function(){ $('#skeletonOverlay').hide(); };

    window.updateBreadcrumb = function(){
        var file = location.pathname.split('/').pop();
        var parent = $('#sidebar .sub-menu.active > a span').first().text().trim();
        var child = $('#sidebar .sub-menu.active .sub a').filter(function(){ return $(this).attr('href')===file; }).first().text().trim();
        if(!child) child = $('.panel-heading').first().text().trim();
        var path = (parent ? parent+' › ' : '') + (child || file);
        $('#crumbPath').text(path);
        var $t = $('table').first();
        var total = $t.find('tbody tr').length;
        var visible = $t.find('tbody tr:visible').length;
        var info = 'تعداد: ' + visible + '/' + total;
        $('#crumbInfo').text(info);
    };

    window.attachSelectionCounter = function(tableSelector, labelSelector){
        function recalc(){ var n=$(tableSelector).find('tbody .checkboxes:checked').length; $(labelSelector).text('انتخاب‌ها: '+n); }
        $(document).on('change', tableSelector+' .checkboxes', recalc);
        $(document).on('keyup', function(){ recalc(); });
        recalc();
    };

    window.setupSavedFilter = function(formSelector, saveBtnSelector, loadBtnSelector, key){
        var k='filter_'+key; $(saveBtnSelector).on('click', function(e){ e.preventDefault(); var data={}; $(formSelector).find('input, select').each(function(){ var n=$(this).attr('name'); if(!n) return; data[n]=$(this).val(); }); localStorage.setItem(k, JSON.stringify(data)); if(window.showToast) showToast('فیلتر ذخیره شد'); }); $(loadBtnSelector).on('click', function(e){ e.preventDefault(); var raw=localStorage.getItem(k); if(!raw){ if(window.showToast) showToast('فیلتری ذخیره نشده'); return; } try{ var data=JSON.parse(raw); var $f=$(formSelector); Object.keys(data).forEach(function(n){ var v=data[n]; $f.find('[name="'+n+'"]').val(v); }); $f.submit(); }catch(ex){ if(window.showToast) showToast('خطا در بارگذاری فیلتر'); } }); };

    window.attachColumnToggles = function(tableSelector, triggerSelector){
        var $btn=$(triggerSelector); if(!$btn.length) return; var $t=$(tableSelector); var $ths=$t.find('thead th'); var $menu=$('<div class="col-menu"></div>').insertAfter($btn).hide(); $ths.each(function(i){ if(i===0) return; var txt=$(this).text().trim()||('ستون '+i); var $lbl=$('<label></label>'); var $cb=$('<input type="checkbox" checked>'); $cb.on('change', function(){ var show=$(this).prop('checked'); $t.find('thead th').eq(i).toggle(show); $t.find('tbody tr').each(function(){ $(this).find('td').eq(i).toggle(show); }); }); $lbl.append($cb).append($('<span></span>').text(txt)); $menu.append($lbl); }); var open=false; $btn.on('click', function(e){ e.preventDefault(); if(!open){ var pos=$btn.offset(); $menu.css({top: pos.top+$btn.outerHeight()+6, left: pos.left}).show(); open=true; } else { $menu.hide(); open=false; } }); $(document).on('click', function(e){ if(open && !$(e.target).closest('.col-menu, '+triggerSelector).length){ $menu.hide(); open=false; } }); };

    $(function(){
        if(window.hideSkeleton) window.hideSkeleton();
        if(window.updateBreadcrumb) window.updateBreadcrumb();
        $(document).on('submit','form',function(){ if(!$(this).attr('target')) window.showSkeleton(); });
        $(document).on('click','a',function(){
            var href = $(this).attr('href');
            if(!href) return;
            if(href.charAt(0)==='#') return;
            if(this.target) return;
            if(href.indexOf('javascript:')===0) return;
            window.showSkeleton();
        });
        window.addEventListener('beforeunload', function(){ window.showSkeleton(); });
        $(document).on('change','.checkboxes', function(){ $(this).closest('tr').toggleClass('selected', $(this).prop('checked')); });
        $(document).on('change','.group-checkable', function(){ var set=$(this).attr('data-set'); var checked=$(this).prop('checked'); $(set).each(function(){ $(this).prop('checked', checked).trigger('change'); }); });
        $(document).on('keydown', function(e){ if(e.shiftKey){ var key=e.key.toLowerCase(); if(key==='s'){ var $t=$('table').first(); $t.find('tbody tr:visible .checkboxes').prop('checked', true).trigger('change'); } else if(key==='d'){ var $t=$('table').first(); $t.find('tbody .checkboxes').prop('checked', false).trigger('change'); } else if(key==='c'){ var btn=$('#invCopy,#usersCopy,#prodCopy,#payCopy').first(); if(btn.length) btn.trigger('click'); } else if(key==='e'){ var btn=$('#invExportVisible,#usersExportVisible,#prodExportVisible,#payExportVisible').first(); if(btn.length) btn.trigger('click'); } else if(key==='p'){ var btn=$('#invPrint,#usersPrint,#prodPrint,#payPrint').first(); if(btn.length) btn.trigger('click'); } } });
    });

    // action grid favorites, drag-and-drop, and command palette
    (function(){
        var $grid = $('.action-grid'); if(!$grid.length) return;
        var favKey='fav_actions', orderKey='order_actions';
        function loadFav(){ try{ return JSON.parse(localStorage.getItem(favKey)||'[]'); }catch(e){ return []; } }
        function saveFav(ids){ localStorage.setItem(favKey, JSON.stringify(ids)); }
        function loadOrder(){ try{ return JSON.parse(localStorage.getItem(orderKey)||'[]'); }catch(e){ return []; } }
        function saveOrder(ids){ localStorage.setItem(orderKey, JSON.stringify(ids)); }
        var favs = loadFav();
        var cards = $grid.find('.action-card');
        cards.each(function(){ var id=$(this).attr('data-action-id'); if(favs.indexOf(id)>=0){ $(this).addClass('fav'); } });
        $grid.on('click','.fav-toggle', function(e){ e.preventDefault(); e.stopPropagation(); var $card=$(this).closest('.action-card'); var id=$card.attr('data-action-id'); var i=favs.indexOf(id); if(i>=0){ favs.splice(i,1); $card.removeClass('fav'); } else { favs.push(id); $card.addClass('fav'); } saveFav(favs); });
        var dragId=null; cards.on('dragstart', function(e){ dragId=$(this).attr('data-action-id'); e.originalEvent.dataTransfer.effectAllowed='move'; });
        cards.on('dragover', function(e){ e.preventDefault(); e.originalEvent.dataTransfer.dropEffect='move'; });
        cards.on('drop', function(e){ e.preventDefault(); var targetId=$(this).attr('data-action-id'); if(!dragId || dragId===targetId) return; var $drag=$grid.find('.action-card[data-action-id="'+dragId+'"]').get(0); var $target=$(this).get(0); $grid.get(0).insertBefore($drag, $target); var ids=[]; $grid.find('.action-card').each(function(){ ids.push($(this).attr('data-action-id')); }); saveOrder(ids); });
        var order=loadOrder(); if(order.length){ order.forEach(function(id){ var el=$grid.find('.action-card[data-action-id="'+id+'"]').get(0); if(el){ $grid.get(0).appendChild(el); } }); }
        var $toolbar = $('.action-toolbar');
        if($toolbar.length){
            $toolbar.on('click','.btn[data-filter]', function(e){ e.preventDefault(); var f=$(this).attr('data-filter');
                if(f==='all'){ cards.show(); }
                else if(f==='fav'){ cards.hide(); $grid.find('.action-card.fav').show(); }
                else { cards.hide(); $grid.find('.action-card.'+f).show(); }
            });
            $('#toggleLayoutEdit').on('click', function(e){ e.preventDefault(); $('body').toggleClass('layout-edit'); if(window.showToast) showToast($('body').hasClass('layout-edit')?'ویرایش فعال شد':'ویرایش غیرفعال شد'); });
            $('#resetLayout').on('click', function(e){ e.preventDefault(); localStorage.removeItem(orderKey); localStorage.removeItem(favKey); if(window.showToast) showToast('چیدمان بازنشانی شد'); location.reload(); });
        }
    })();

    // brand theme customization (CSS variables via localStorage)
    (function(){
        var key='brand_theme';
        function applyTheme(t){ if(!t) return; var root=document.documentElement; Object.keys(t).forEach(function(k){ root.style.setProperty('--'+k, t[k]); }); }
        try{ var saved = localStorage.getItem(key); if(saved){ applyTheme(JSON.parse(saved)); } }catch(ex){}
        window.saveBrandTheme = function(t){ try{ localStorage.setItem(key, JSON.stringify(t)); applyTheme(t); if(window.showToast) showToast('رنگ‌ها ذخیره شد'); }catch(ex){} };
        window.resetBrandTheme = function(){ localStorage.removeItem(key); location.reload(); };
        var $p = $('#brandPrimary'); if($p.length){
            $('#brandSave').on('click', function(e){ e.preventDefault(); var t={
                'btn-primary': $('#brandPrimary').val(),
                'btn-success': $('#brandSuccess').val(),
                'btn-danger': $('#brandDanger').val(),
                'btn-warning': $('#brandWarning').val(),
                'btn-info': $('#brandInfo').val(),
                'btn-default': $('#brandDefault').val(),
                'accent-color': $('#brandAccent').val()
            }; window.saveBrandTheme(t); });
            $('#brandReset').on('click', function(e){ e.preventDefault(); window.resetBrandTheme(); });
        }
    })();

}();
    // menu density toggle and persistence
    (function(){
        var key='menuDensity';
        var saved = localStorage.getItem(key);
        if(saved==='compact'){ document.body.classList.add('sidebar-compact'); }
        $('#toggleDensity').on('click', function(e){ e.preventDefault(); var c=document.body.classList.toggle('sidebar-compact'); localStorage.setItem(key, c?'compact':'normal'); if(window.showToast) showToast(c?'حالت فشرده فعال شد':'حالت فشرده غیرفعال شد'); });
    })();

    // role-based visibility
    (function(){
        var rank = { user:1, manager:2, admin:3 };
        var userRole = (window.USER_ROLE||'admin').toLowerCase();
        var userRank = rank[userRole]||3;
        $('#sidebar ul.sidebar-menu > li').each(function(){ var need = $(this).attr('data-role'); if(!need) return; var n = rank[need]||1; if(userRank < n) $(this).hide(); });
    })();

    // drag & drop reorder for sidebar
    (function(){
        var key='sidebarOrder';
        var $list = $('#sidebar ul.sidebar-menu'); if(!$list.length) return;
        function idOf(li){ return $(li).attr('data-id') || $(li).find('> a span').first().text().trim(); }
        function applyOrder(ids){ var map={}; ids.forEach(function(id){ map[id]=true; }); $list.children('li').sort(function(a,b){ var ia=ids.indexOf(idOf(a)); var ib=ids.indexOf(idOf(b)); return (ia===-1?9999:ia)-(ib===-1?9999:ib); }).appendTo($list); }
        var saved = []; try{ saved = JSON.parse(localStorage.getItem(key)||'[]'); }catch(e){}
        if(saved && saved.length){ applyOrder(saved); }
        function enableDrag(enable){ $list.children('li').attr('draggable', enable? 'true':'false'); }
        enableDrag(false);
        $('#toggleLayoutEdit').on('click', function(){ var on = $('body').toggleClass('layout-edit').hasClass('layout-edit'); enableDrag(on); });
        var dragSrc;
        $list.on('dragstart','> li', function(e){ dragSrc=this; e.originalEvent.dataTransfer.setData('text/plain', idOf(this)); });
        $list.on('dragover','> li', function(e){ e.preventDefault(); });
        $list.on('drop','> li', function(e){ e.preventDefault(); if(!dragSrc || dragSrc===this) return; var ids=$list.children('li').map(function(){ return idOf(this); }).get(); var from=ids.indexOf(idOf(dragSrc)); var to=ids.indexOf(idOf(this)); if(from>-1 && to>-1){ ids.splice(to,0,ids.splice(from,1)[0]); applyOrder(ids); localStorage.setItem(key, JSON.stringify(ids)); }
        });
    })();
    // toolbar buttons actions
    (function(){
        var $tb = $('.action-toolbar'); if(!$tb.length) return;
        function firstForm(){ var $f = $('form').first(); return $f.length ? $f : null; }
        function findEditLink(){ var $lnk = $('a[href*="edit"], a:contains("ویرایش")').first(); return $lnk.length ? $lnk.attr('href') : null; }
        function scrollToSales(){ var $c = $('.chart-card[data-chart="sales"]'); if($c.length){ $('html, body').animate({scrollTop: $c.offset().top-80}, 300); } }
        $tb.on('click','[data-action]', function(e){ e.preventDefault(); var act=$(this).attr('data-action');
            if(act==='save'){ var $f=firstForm(); if($f){ showToast('در حال ذخیره...'); $f.trigger('submit'); } else { showToast('فرمی برای ذخیره یافت نشد'); } }
            else if(act==='delete'){ var n=$('.checkboxes:checked').length; if(n===0){ showToast('موردی انتخاب نشده'); return; } if(confirm('حذف '+n+' مورد؟')) showToast('حذف انجام شد'); }
            else if(act==='edit'){ var href=findEditLink(); if(href){ location.href=href; } else { showToast('لینک ویرایش یافت نشد'); } }
            else if(act==='search'){ var $g=$('#globalSearch'); if($g.length){ $g.focus(); } }
            else if(act==='back'){ history.back(); }
            else if(act==='next'){ history.forward(); }
            else if(act==='pdf'){ showToast('تهیه خروجی PDF'); window.print(); }
            else if(act==='report'){ showToast('نمایش گزارش'); scrollToSales(); }
            else if(act==='adv-settings'){ location.href='settings.php'; }
        });

        // dynamic enable/disable
        function hasForm(){ return $('form').first().length>0; }
        function selectedCount(){ return $('.checkboxes:checked').length; }
        function hasEdit(){ return $('a[href*="edit"], a:contains("ویرایش")').length>0; }
        function setDisabled(action, disabled){ var $b=$tb.find('[data-action="'+action+'"]').attr('aria-disabled', disabled?'true':'false'); }
        function recalc(){ setDisabled('save', !hasForm()); setDisabled('delete', selectedCount()===0); setDisabled('edit', !hasEdit()); }
        $(document).on('change','.checkboxes', recalc);
        $(function(){ recalc(); });

        // keyboard shortcuts
        $(document).on('keydown', function(e){
            var k = (e.key||'').toLowerCase();
            if((e.ctrlKey||e.metaKey) && k==='s'){ e.preventDefault(); $tb.find('[data-action="save"]').trigger('click'); }
            else if(k==='delete' && selectedCount()>0){ e.preventDefault(); $tb.find('[data-action="delete"]').trigger('click'); }
            else if((e.ctrlKey||e.metaKey) && k==='f'){ e.preventDefault(); $tb.find('[data-action="search"]').trigger('click'); }
            else if(e.altKey && k==='arrowleft'){ e.preventDefault(); $tb.find('[data-action="back"]').trigger('click'); }
            else if(e.altKey && k==='arrowright'){ e.preventDefault(); $tb.find('[data-action="next"]').trigger('click'); }
        });
    })();
