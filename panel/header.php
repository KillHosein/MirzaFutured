<!--header start-->
      <header class="header white-bg">
          <div>
            <div class="sidebar-toggle-box">
                <div data-original-title="Toggle Navigation" data-placement="right" class="icon-reorder tooltips"></div>
            </div>
            <!--logo start-->
            <a href="#" class="logo">ربات <span>میرزا</span></a>
            <!--logo end-->
            <div class="nav notify-row" id="top_menu">
            </div>
            </div>
            <div class="top-nav ">
                <!--search & user info start-->
                <ul class="nav pull-right top-menu">
                    <li>
                        <a id="themeToggle" href="#" title="حالت تیره">
                            <i class="icon-moon"></i>
                        </a>
                    </li>
                    <!-- user login dropdown start-->
                    <li class="dropdown">
                        <a data-toggle="dropdown" class="dropdown-toggle" href="#">
                            <img alt="" src="img/avatar1_small.jpg">
                            <span class="username">سلام <?php echo $_SESSION["user"]; ?></span>
                            <b class="caret"></b>
                        </a>
                        <ul class="dropdown-menu extended logout">
                            <div class="log-arrow-up"></div>
                            <li><a href="#"><i class="icon-cog"></i> تنظیمات</a></li>
                            <li><a href="login.php"><i class="icon-key"></i> خروج</a></li>
                        </ul>
                    </li>
                    <!-- user login dropdown end -->
                </ul>
                <!--search & user info end-->
            </div>
        </header>
      <!--header end-->
      <!--sidebar start-->
      <aside>
          <div id="sidebar"  class="nav-collapse ">
              <!-- sidebar menu start-->
              <?php $current = basename($_SERVER['PHP_SELF']); ?>
              <ul class="sidebar-menu">
                  <li class="<?php echo $current==='index.php'?'active':''; ?>">
                      <a href="index.php">
                          <i class="icon-dashboard"></i>
                          <span>صفحه اصلی</span>
                      </a>
                  </li>
                  <li class="sub-menu <?php echo in_array($current,['users.php','user.php'])?'active':''; ?>">
                      <a href="javascript:;" class="">
                          <i class="icon-user"></i>
                          <span>کاربران</span>
                          <span class="arrow"></span>
                      </a>
                      <ul class="sub">
                          <li><a class="" href="users.php">لیست کاربران</a></li>
                          <li><a class="" href="user.php">مدیریت کاربر</a></li>
                      </ul>
                  </li>
                  <li class="sub-menu <?php echo in_array($current,['invoice.php','payment.php','service.php','product.php','productedit.php'])?'active':''; ?>">
                      <a href="javascript:;" class="">
                          <i class="icon-briefcase"></i>
                          <span>مدیریت سرویس‌ها</span>
                          <span class="arrow"></span>
                      </a>
                      <ul class="sub">
                          <li><a class="" href="invoice.php">سفارشات</a></li>
                          <li><a class="" href="payment.php">پرداخت‌ها</a></li>
                          <li><a class="" href="service.php">سرویس‌ها</a></li>
                          <li><a class="" href="product.php">محصولات</a></li>
                          <li><a class="" href="productedit.php">ویرایش محصول</a></li>
                      </ul>
                  </li>
                  <li class="sub-menu <?php echo in_array($current,['keyboard.php','seeting_x_ui.php','inbound.php','cancelService.php'])?'active':''; ?>">
                      <a href="javascript:;" class="">
                          <i class="icon-cogs"></i>
                          <span>پیکربندی</span>
                          <span class="arrow"></span>
                      </a>
                      <ul class="sub">
                          <li><a class="" href="keyboard.php">چیدمان کیبورد</a></li>
                          <li><a class="" href="seeting_x_ui.php">تنظیمات x-ui</a></li>
                          <li><a class="" href="inbound.php">ورودی‌ها</a></li>
                          <li><a class="" href="cancelService.php">حذف سرویس</a></li>
                      </ul>
                  </li>
                  <!--<li class="sub-menu">-->
                  <!--    <a href="javascript:;" class="">-->
                  <!--        <i class="icon-user"></i>-->
                  <!--        <span>کاربران</span>-->
                  <!--        <span class="arrow"></span>-->
                  <!--    </a>-->
                  <!--    <ul class="sub">-->
                  <!--        <li><a class="" href="users.php">لیست کاربران</a></li>-->
                  <!--    </ul>-->
                  <!--</li>-->
              </ul>
              <!-- sidebar menu end-->
          </div>
      </aside>
      <!--sidebar end-->
