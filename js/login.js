/* =========================================================
   MYPORTFOLIO LOGIN / LANDING PAGE JAVASCRIPT
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    /*
     * =======================================================
     * CLOSE MOBILE NAVBAR AFTER CLICKING A LINK
     * =======================================================
     */

    const navLinks = document.querySelectorAll(
        ".mp-navbar .nav-link"
    );

    const navbarCollapse = document.getElementById(
        "mainNavbar"
    );


    navLinks.forEach(function (link) {

        link.addEventListener("click", function () {

            if (
                navbarCollapse &&
                navbarCollapse.classList.contains("show")
            ) {

                const collapse =
                    bootstrap.Collapse.getInstance(
                        navbarCollapse
                    );

                if (collapse) {
                    collapse.hide();
                }

            }

        });

    });


    /*
     * =======================================================
     * GOOGLE LOGIN BUTTON
     * =======================================================
     */

    const googleLoginButton =
        document.querySelector(
            ".google-login-button"
        );


    if (googleLoginButton) {

        googleLoginButton.addEventListener(
            "click",
            function () {

                /*
                 * Prevent accidental double clicks.
                 */

                googleLoginButton.classList.add(
                    "disabled"
                );

                googleLoginButton.style.pointerEvents =
                    "none";

                const originalText =
                    googleLoginButton.querySelector(
                        ".google-button-text"
                    );


                if (originalText) {

                    originalText.textContent =
                        "Connecting to Google...";

                }

            }
        );

    }


    /*
     * =======================================================
     * INTERSECTION OBSERVER
     * =======================================================
     *
     * Adds a subtle reveal effect when sections enter
     * the viewport.
     */

    const revealElements =
        document.querySelectorAll(
            ".feature-card, .about-main-card, .contact-card"
        );


    if ("IntersectionObserver" in window) {

        const observer =
            new IntersectionObserver(
                function (entries, observer) {

                    entries.forEach(function (entry) {

                        if (entry.isIntersecting) {

                            entry.target.style.opacity =
                                "1";

                            entry.target.style.transform =
                                "translateY(0)";

                            observer.unobserve(
                                entry.target
                            );

                        }

                    });

                },
                {
                    threshold: 0.10
                }
            );


        revealElements.forEach(function (element) {

            element.style.opacity = "0";

            element.style.transform =
                "translateY(15px)";

            element.style.transition =
                "opacity 0.5s ease, transform 0.5s ease";

            observer.observe(element);

        });

    }

});