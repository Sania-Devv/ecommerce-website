
<footer class="footer">

    <!-- Newsletter / Deals -->
    <div class="cta bg-image bg-dark pt-4 pb-5 mb-0"
        style="background-image: url(assets/images/demos/demo-4/bg-5.jpg);">

        <div class="container">
            <div class="row justify-content-center">

                <div class="col-sm-10 col-md-8 col-lg-6">

                    <div class="cta-heading text-center">
                        <h3 class="cta-title text-white">
                            Get The Latest Deals
                        </h3>

                        <p class="cta-desc text-white">
                            and receive <span class="font-weight-normal">$20 coupon</span>
                            for first shopping
                        </p>
                    </div>

                    <form action="#">
                        <div class="input-group input-group-round">

                            <input
                                type="email"
                                class="form-control form-control-white"
                                placeholder="Enter your Email Address"
                                aria-label="Email Address"
                                required
                            >

                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit">
                                    <span>Subscribe</span>
                                    <i class="icon-long-arrow-right"></i>
                                </button>
                            </div>

                        </div>
                    </form>

                </div>

            </div>
        </div>
    </div>


    <!-- Footer Middle -->
    <div class="footer-middle">

        <div class="container">

            <div class="row">

                <!-- ShopMart About / Social -->
                <div class="col-sm-6 col-lg-3">

                    <div class="widget widget-about">

                        <!-- ShopMart Logo -->
                        <a href="index.php" class="footer-brand">
                            <i class="icon-shopping-bag"></i>
                            <span>Shop<span>Mart</span></span>
                        </a>

                        <p>
                            ShopMart brings you quality products, easy shopping,
                            secure checkout and reliable delivery — all in one place.
                        </p>

                        <!-- Social Icons -->
                        <div class="social-icons">

                            <a href="#"
                                class="social-icon"
                                title="Facebook"
                                target="_blank">
                                <i class="icon-facebook-f"></i>
                            </a>

                            <a href="#"
                                class="social-icon"
                                title="Twitter"
                                target="_blank">
                                <i class="icon-twitter"></i>
                            </a>

                            <a href="#"
                                class="social-icon"
                                title="Instagram"
                                target="_blank">
                                <i class="icon-instagram"></i>
                            </a>

                            <a href="#"
                                class="social-icon"
                                title="Youtube"
                                target="_blank">
                                <i class="icon-youtube"></i>
                            </a>

                            <a href="#"
                                class="social-icon"
                                title="Pinterest"
                                target="_blank">
                                <i class="icon-pinterest"></i>
                            </a>

                        </div>

                    </div>
                </div>


                <!-- ShopMart Navigation -->
                <div class="col-sm-6 col-lg-3">

                    <div class="widget">

                        <h4 class="widget-title">
                            ShopMart
                        </h4>

                        <ul class="widget-list">

                            <li>
                                <a href="index.php">
                                    Home
                                </a>
                            </li>

                            <li>
                                <a href="products.php">
                                    Shop
                                </a>
                            </li>

                            <li>
                                <a href="cart.php">
                                    Cart
                                </a>
                            </li>

                        </ul>

                    </div>
                </div>


                <!-- Customer Service -->
                <div class="col-sm-6 col-lg-3">

                    <div class="widget">

                        <h4 class="widget-title">
                            Customer Service
                        </h4>

                        <ul class="widget-list">

                            <li>
                                <a>
                                    Payment Methods
                                </a>
                            </li>

                            <li>
                                <a >
                                    Returns
                                </a>
                            </li>

                            <li>
                                <a >
                                    Shipping
                                </a>
                            </li>

                        </ul>

                    </div>
                </div>


                <!-- My Account -->
                <div class="col-sm-6 col-lg-3">

                    <div class="widget">

                        <h4 class="widget-title">
                            My Account
                        </h4>

                        <ul class="widget-list">

                            <li>
                                <a href="login.php">
                                    Sign In
                                </a>
                            </li>

                            <li>
                                <a href="cart.php">
                                    View Cart
                                </a>
                            </li>

                        </ul>

                    </div>
                </div>

            </div>
        </div>
    </div>


    <!-- Footer Bottom -->
    <div class="footer-bottom">

        <div class="container">

            <p class="footer-copyright">
                Copyright © 2026 ShopMart. All Rights Reserved.
            </p>

            <figure class="footer-payments">

                <img
                    src="assets/images/payments.png"
                    alt="Payment methods"
                    width="272"
                    height="20"
                >

            </figure>

        </div>
    </div>

</footer>


<style>

/* =========================================================
   SHOPMART FOOTER LOGO
   Same Blue as Header
   ========================================================= */

.footer-brand {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    text-decoration: none !important;
    margin-bottom: 18px;
}

.footer-brand i {
    font-size: 25px;
    color: #3399ff;
    line-height: 1;
}

.footer-brand > span {
    font-size: 26px;
    font-weight: 800;
    letter-spacing: -0.5px;
    color: #222;
    line-height: 1;
}

.footer-brand > span > span {
    color: #3399ff;
}

.footer-brand:hover i,
.footer-brand:hover > span > span {
    color: #3399ff;
}


/* Footer Links */

.footer .widget-list a:hover {
    color: #3399ff;
}


/* Newsletter Button */

.footer .btn-primary {
    background-color: #3399ff;
    border-color: #3399ff;
}

.footer .btn-primary:hover {
    background-color: #2288ee;
    border-color: #2288ee;
}


/* Social Icons */

.footer .social-icon:hover {
    color: #3399ff;
}


/* Mobile */

@media (max-width: 575px) {

    .footer-brand i {
        font-size: 21px;
    }

    .footer-brand > span {
        font-size: 21px;
    }

}

</style>

