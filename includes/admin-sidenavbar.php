<?php 
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); 
?> 
 
<style> 
#sidenav-main { 
    overflow: hidden !important; 
    height: 100vh !important; 
    max-height: 100vh !important; 
    -ms-overflow-style: none !important; 
    scrollbar-width: none !important; 
} 
 
#sidenav-collapse-main { 
    overflow: hidden !important; 
    height: 100% !important; 
    max-height: 100% !important; 
    -ms-overflow-style: none !important; 
    scrollbar-width: none !important; 
} 
 
#sidenav-main .navbar-nav { 
    overflow: hidden !important; 
} 
 
#sidenav-main::-webkit-scrollbar, 
#sidenav-collapse-main::-webkit-scrollbar, 
#sidenav-main .navbar-nav::-webkit-scrollbar { 
    display: none !important; 
    width: 0 !important; 
    height: 0 !important; 
} 
 
#sidenav-main .ps__rail-y, 
#sidenav-main .ps__rail-x, 
#sidenav-main .ps__thumb-y, 
#sidenav-main .ps__thumb-x, 
#sidenav-main .scrollbar-track, 
#sidenav-main .scrollbar-thumb { 
    display: none !important; 
} 


/* ========================================= 
   SHOPMART ADMIN LOGO
   ========================================= */ 

.shopmart-admin-brand { 
    display: inline-flex !important; 
    align-items: center; 
    text-decoration: none !important; 
} 
 
.shopmart-admin-brand-name { 
    font-size: 26px; 
    font-weight: 800; 
    letter-spacing: -0.5px; 
    color: #222; 
    line-height: 1; 
} 
 
.shopmart-admin-brand-name span { 
    color: #3399ff; 
} 
</style> 
 
 
<aside class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2 bg-white my-2" 
       id="sidenav-main"> 
 
    <!-- Logo / Brand --> 
    <div class="sidenav-header"> 
 
        <i class="fas fa-times p-3 cursor-pointer text-dark opacity-5 position-absolute end-0 top-0 d-none d-xl-none" 
           aria-hidden="true" 
           id="iconSidenav"></i> 
 
        <a class="navbar-brand px-4 py-3 m-0 shopmart-admin-brand" 
           href="/admin/index.php"> 
 
            <span class="shopmart-admin-brand-name"> 
                Shop<span>Mart</span> 
            </span> 
 
        </a> 
 
    </div> 
 
 
    <hr class="horizontal dark mt-0 mb-2"> 
 
 
    <!-- Navigation --> 
    <div class="collapse navbar-collapse w-auto" 
         id="sidenav-collapse-main"> 
 
        <ul class="navbar-nav"> 
 
 
            <!-- Dashboard --> 
            <li class="nav-item"> 
 
                <a class="nav-link <?= $currentPath === '/admin/index.php' ? 'active bg-gradient-dark text-white' : 'text-dark' ?>" 
                   href="/admin/index.php"> 
 
                    <i class="material-symbols-rounded opacity-5"> 
                        dashboard 
                    </i> 
 
                    <span class="nav-link-text ms-1"> 
                        Dashboard 
                    </span> 
 
                </a> 
 
            </li> 
 
 
            <!-- Categories --> 
            <li class="nav-item"> 
 
                <a class="nav-link <?= str_starts_with($currentPath, '/admin/categories/') ? 'active bg-gradient-dark text-white' : 'text-dark' ?>" 
                   href="/admin/categories/index.php"> 
 
                    <i class="material-symbols-rounded opacity-5"> 
                        category 
                    </i> 
 
                    <span class="nav-link-text ms-1"> 
                        Categories 
                    </span> 
 
                </a> 
 
            </li> 
 
 
            <!-- Sliders --> 
            <li class="nav-item"> 
 
                <a class="nav-link <?= str_starts_with($currentPath, '/admin/sliders/') ? 'active bg-gradient-dark text-white' : 'text-dark' ?>" 
                   href="/admin/sliders/index.php"> 
 
                    <i class="material-symbols-rounded opacity-5"> 
                        view_carousel 
                    </i> 
 
                    <span class="nav-link-text ms-1"> 
                        Sliders 
                    </span> 
 
                </a> 
 
            </li> 
 
 
            <!-- Products --> 
            <li class="nav-item"> 
 
                <a class="nav-link <?= str_starts_with($currentPath, '/admin/products/') ? 'active bg-gradient-dark text-white' : 'text-dark' ?>" 
                   href="/admin/products/index.php"> 
 
                    <i class="material-symbols-rounded opacity-5"> 
                        inventory_2 
                    </i> 
 
                    <span class="nav-link-text ms-1"> 
                        Products 
                    </span> 
 
                </a> 
 
            </li> 
 
 
            <!-- Deals --> 
            <li class="nav-item"> 
 
                <a class="nav-link text-dark" 
                   href="/admin/deals/index.php"> 
 
                    <div class="text-dark text-center me-2 d-flex align-items-center justify-content-center"> 
 
                        <i class="material-symbols-rounded opacity-5"> 
                            local_offer 
                        </i> 
 
                    </div> 
 
                    <span class="nav-link-text ms-1"> 
                        Deals 
                    </span> 
 
                </a> 
 
            </li> 
 
 
            <!-- Users --> 
            <li class="nav-item"> 
 
                <a class="nav-link <?= str_starts_with($currentPath, '/admin/users/') ? 'active bg-gradient-dark text-white' : 'text-dark' ?>" 
                   href="/admin/users/index.php"> 
 
                    <i class="material-symbols-rounded opacity-5"> 
                        people 
                    </i> 
 
                    <span class="nav-link-text ms-1"> 
                        Users 
                    </span> 
 
                </a> 
 
            </li> 
 
 
            <!-- Orders --> 
            <li class="nav-item"> 
 
                <a class="nav-link <?= str_starts_with($currentPath, '/admin/orders/') ? 'active bg-gradient-dark text-white' : 'text-dark' ?>" 
                   href="/admin/orders/index.php"> 
 
                    <i class="material-symbols-rounded opacity-5"> 
                        shopping_cart 
                    </i> 
 
                    <span class="nav-link-text ms-1"> 
                        Orders 
                    </span> 
 
                </a> 
 
            </li> 
 
 
            <!-- Reports --> 
            <li class="nav-item"> 
 
                <a class="nav-link <?= $currentPath === '/admin/reports.php' ? 'active bg-gradient-dark text-white' : 'text-dark' ?>" 
                   href="/admin/reports.php"> 
 
                    <i class="material-symbols-rounded opacity-5"> 
                        bar_chart 
                    </i> 
 
                    <span class="nav-link-text ms-1"> 
                        Reports 
                    </span> 
 
                </a> 
 
            </li> 
 
 
            <!-- Account Section --> 
            <li class="nav-item mt-3"> 
 
                <h6 class="ps-4 ms-2 text-uppercase text-xs text-dark font-weight-bolder opacity-5"> 
                    Account 
                </h6> 
 
            </li> 
 
 
            <!-- Profile --> 
            <li class="nav-item"> 
 
                <a class="nav-link <?= $currentPath === '/admin/profile.php' ? 'active bg-gradient-dark text-white' : 'text-dark' ?>" 
                   href="/admin/profile.php"> 
 
                    <i class="material-symbols-rounded opacity-5"> 
                        person 
                    </i> 
 
                    <span class="nav-link-text ms-1"> 
                        Profile 
                    </span> 
 
                </a> 
 
            </li> 
 
 
            <!-- Sign In --> 
            <!-- 
            <li class="nav-item"> 
 
                <a class="nav-link <?= $currentPath === '/admin/login.php' ? 'active bg-gradient-dark text-white' : 'text-dark' ?>" 
                   href="/admin/login.php"> 
 
                    <i class="material-symbols-rounded opacity-5"> 
                        login 
                    </i> 
 
                    <span class="nav-link-text ms-1"> 
                        Sign In 
                    </span> 
 
                </a> 
 
            </li> 
            --> 
 
 
            <!-- Sign Up --> 
            <!-- 
            <li class="nav-item"> 
 
                <a class="nav-link <?= $currentPath === '/admin/register.php' ? 'active bg-gradient-dark text-white' : 'text-dark' ?>" 
                   href="/admin/register.php"> 
 
                    <i class="material-symbols-rounded opacity-5"> 
                        assignment 
                    </i> 
 
                    <span class="nav-link-text ms-1"> 
                        Sign Up 
                    </span> 
 
                </a> 
 
            </li> 
            --> 
 
 
            <!-- Logout --> 
            <li class="nav-item"> 
 
                <form action="/public/logout.php" 
                      method="POST" 
                      class="m-0"> 
 
                    <button type="submit" 
                            class="nav-link text-dark border-0 bg-transparent w-100 text-start"> 
 
                        <i class="material-symbols-rounded opacity-5"> 
                            logout 
                        </i> 
 
                        <span class="nav-link-text ms-1"> 
                            Logout 
                        </span> 
 
                    </button> 
 
                </form> 
 
            </li> 
 
 
        </ul> 
 
    </div> 
 
</aside>