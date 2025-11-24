var Script = function () {



//    sidebar dropdown menu

    jQuery('#sidebar .sub-menu > a').click(function () {
        var last = jQuery('.sub-menu.open', $('#sidebar'));
        last.removeClass("open");
        jQuery('.arrow', last).removeClass("open");
        jQuery('.sub', last).slideUp(200);
        var sub = jQuery(this).next();
        if (sub.is(":visible")) {
            jQuery('.arrow', jQuery(this)).removeClass("open");
            jQuery(this).parent().removeClass("open");
            sub.slideUp(200);
        } else {
            jQuery('.arrow', jQuery(this)).addClass("open");
            jQuery(this).parent().addClass("open");
            sub.slideDown(200);
        }
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
        var saved = localStorage.getItem(key);
        if(saved === 'dark') {
            document.body.classList.add('dark');
        }
        $('#themeToggle').on('click', function(e){
            e.preventDefault();
            var isDark = document.body.classList.toggle('dark');
            localStorage.setItem(key, isDark ? 'dark' : 'light');
        });
    })();

    // global search in sidebar
    (function(){
        var $input = $('#globalSearch');
        if(!$input.length) return;
        var $items = $('#sidebar ul.sidebar-menu > li');
        $input.on('keyup', function(){
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
                    }
                } else {
                    $li.hide();
                }
            });
        });
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
    });

}();
