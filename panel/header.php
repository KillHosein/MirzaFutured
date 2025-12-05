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
                        <input id="globalSearch" type="text" class="search" placeholder="جستجو در پنل..." />
                    </li>
                    <li class="dropdown" id="notifDropdown">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" title="اعلان‌ها">
                            <i class="icon-bell"></i>
                            <span id="notifBadge" class="badge badge-warning" style="display:none;">0</span>
                        </a>
                        <ul class="dropdown-menu extended notification" style="width:320px;">
                            <div class="notify-arrow"></div>
                            <li>
                                <p id="notifTitle">اعلان‌های جدید</p>
                            </li>
                            <li>
                                <div id="notifList" style="max-height:280px; overflow:auto;"></div>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <a id="themeToggle" href="#" title="حالت تیره">
                            <i class="icon-moon"></i>
                        </a>
                    </li>
                    <!-- user login dropdown start-->
                    <li class="dropdown">
                        <a data-toggle="dropdown" class="dropdown-toggle" href="#">
                            <img alt="" src="img/avatar1_small.jpg" loading="lazy" decoding="async">
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
              <?php
              $count_users = 0; $count_orders = 0; $count_payments = 0;
              try {
                  if(isset($pdo)){
                      $count_users = (int)$pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
                      $count_orders = (int)$pdo->query("SELECT COUNT(*) FROM invoice")->fetchColumn();
                      $count_payments = (int)$pdo->query("SELECT COUNT(*) FROM Payment_report")->fetchColumn();
                  }
              } catch(Exception $e) {}
              ?>
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
                          <span class="menu-badge"><?php echo number_format($count_users); ?></span>
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
                          <span class="menu-badge"><?php echo number_format($count_orders); ?></span>
                          <span class="arrow"></span>
                      </a>
                      <ul class="sub">
                          <li><a class="" href="invoice.php">سفارشات</a></li>
                          <li><a class="" href="payment.php">پرداخت‌ها <span class="menu-badge"><?php echo number_format($count_payments); ?></span></a></li>
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
                          <li><a class="" href="settings.php">تنظیمات ادمین</a></li>
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
      <div class="app-toolbar" role="toolbar" aria-label="نوار ابزار">
        <div class="action-toolbar">
          <a href="index.php" class="btn" aria-label="داشبورد"><i class="icon-dashboard"></i><span>داشبورد</span></a>
          <a href="#" class="btn" data-filter="all" aria-label="نمایش همه">همه</a>
          <a href="#" class="btn" data-filter="fav" aria-label="نمایش منتخب">منتخب</a>
          <a href="#" class="btn" id="toggleLayoutEdit" aria-label="ویرایش چیدمان">ویرایش چیدمان</a>
          <a href="#" class="btn btn-default" id="resetLayout" aria-label="بازنشانی چیدمان">بازنشانی</a>
        </div>
      </div>
      <div class="breadcrumb-bar" id="breadcrumbBar" style="padding:10px 16px;">
        <span id="crumbPath" style="font-weight:700;"></span>
        <span id="crumbInfo" class="text-muted" style="margin-right:8px;"></span>
      </div>
      <div class="fab-container">
        <div class="fab" id="fabToggle" title="اقدامات سریع"><i class="icon-plus"></i></div>
        <div class="fab-menu" id="fabMenu">
          <a class="action" href="index.php"><i class="icon-dashboard"></i><span>داشبورد</span></a>
          <a class="action" href="users.php"><i class="icon-user"></i><span>لیست کاربران</span></a>
          <a class="action" href="product.php#addproduct"><i class="icon-tag"></i><span>افزودن محصول</span></a>
          <a class="action" href="product.php"><i class="icon-tags"></i><span>لیست محصولات</span></a>
          <a class="action" href="invoice.php"><i class="icon-table"></i><span>سفارشات</span></a>
          <a class="action" href="settings.php"><i class="icon-cog"></i><span>تنظیمات ادمین</span></a>
          <a class="action" href="keyboard.php"><i class="icon-th"></i><span>کیبورد ربات</span></a>
          <a class="action" href="inbound.php"><i class="icon-download-alt"></i><span>ورودی‌ها</span></a>
        </div>
      </div>
