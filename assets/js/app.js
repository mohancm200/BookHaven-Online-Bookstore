document.addEventListener("DOMContentLoaded", () => {
    "use strict";

    const CART_KEY = "bookhaven_cart";
    const WISHLIST_API = "api/wishlist.php";

    const $ = (selector, parent = document) =>
        parent.querySelector(selector);

    const $$ = (selector, parent = document) =>
        [...parent.querySelectorAll(selector)];

    function loadCart() {
        try {
            const saved = JSON.parse(localStorage.getItem(CART_KEY));
            return Array.isArray(saved) ? saved : [];
        } catch (error) {
            console.warn("Could not read the saved cart:", error);
            return [];
        }
    }

    let cart = loadCart();
    let wishlist = [];
    let wishlistLoaded = false;
    let wishlistLoadPromise = null;
    let wishlistError = "";

    function saveCart() {
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
    }

    function safe(value) {
        return String(value ?? "").replace(/[&<>"']/g, char => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        })[char]);
    }

    function toast(message) {
        const element = $("#toast");

        if (!element) {
            console.info(message);
            return;
        }

        element.textContent = message;
        element.classList.add("show");
        clearTimeout(toast.timer);

        toast.timer = setTimeout(() => {
            element.classList.remove("show");
        }, 2500);
    }

    function imageUrl(value) {
        const file = String(value ?? "").trim();

        if (!file) return "";

        if (
            /^(https?:)?\/\//i.test(file) ||
            file.startsWith("/") ||
            file.startsWith("data:") ||
            file.startsWith("uploads/") ||
            file.startsWith("assets/")
        ) {
            return file;
        }

        return "uploads/book_covers/" +
            encodeURIComponent(file.split(/[\\/]/).pop());
    }

    // READ BOOK DETAILS FROM THE PRODUCT CARD
    function getBook(id) {
        const card = $$(".product-card").find(element =>
            String(element.dataset.bookId) === String(id)
        );

        if (!card) {
            console.warn("Book card not found on this page:", id);
            return null;
        }

        const title = $(".product-details h3", card);
        const author = $(".product-details p", card);
        const price = $(".product-bottom strong", card);
        const image = $(".product-cover img", card);

        if (!title || !price) return null;

        return {
            id: String(id),
            title: title.textContent.trim(),
            author: author?.textContent.trim() || "Unknown author",
            price: Number(
                price.textContent.replace(/[^\d.]/g, "")
            ) || 0,
            image: imageUrl(image?.getAttribute("src") || ""),
            stock: Math.max(0, Number(card.dataset.stock) || 0)
        };
    }

    // ==========================================
    // WISHLIST DRAWER: CREATE / STYLE
    // ==========================================

    function ensureWishlistPanel() {
        let panel = $("#bookhavenWishlistPanel");

        // index.php already contains the panel.
        // books.php does not, so create it there.
        if (!panel) {
            panel = document.createElement("div");

            panel.id = "bookhavenWishlistPanel";
            panel.setAttribute("role", "dialog");
            panel.setAttribute("aria-modal", "true");
            panel.setAttribute(
                "aria-labelledby",
                "bookhavenWishlistTitle"
            );

            panel.innerHTML = `
                <div class="bookhaven-wishlist-box">

                    <div class="bookhaven-wishlist-header">

                        <h2 id="bookhavenWishlistTitle">
                            Your Wishlist ♥
                        </h2>

                        <button
                            type="button"
                            id="closeBookhavenWishlist"
                            aria-label="Close wishlist"
                        >×</button>

                    </div>

                    <div id="bookhavenWishlistItems">
                        <p class="wishlist-empty">
                            Loading your wishlist...
                        </p>
                    </div>

                </div>
            `;

            document.body.appendChild(panel);
        }

        // Correct drawer styling.
        // This also fixes the invalid escaped selector
        // in the existing style.css.
        if (!$("#bookhavenWishlistDrawerStyles")) {

            const style = document.createElement("style");

            style.id = "bookhavenWishlistDrawerStyles";

            style.textContent = `

                #bookhavenWishlistPanel {
                    display: none !important;
                    position: fixed !important;
                    inset: 0 !important;
                    z-index: 99999 !important;
                    width: 100% !important;
                    height: 100vh !important;
                    height: 100dvh !important;
                    padding: 0 !important;
                    align-items: stretch !important;
                    justify-content: flex-end !important;
                    background: rgba(0,0,0,.72) !important;
                }

                #bookhavenWishlistPanel.open {
                    display: flex !important;
                }

                #bookhavenWishlistPanel .bookhaven-wishlist-box {
                    width: min(500px, 94vw) !important;
                    height: 100% !important;
                    max-height: 100% !important;
                    margin: 0 !important;
                    padding: 25px !important;
                    overflow-y: auto !important;
                    box-sizing: border-box !important;
                    background: #101d2d !important;
                    color: #fff !important;
                    border: 0 !important;
                    border-left: 1px solid #e6a65d !important;
                    border-radius: 0 !important;
                    animation: bookhavenWishlistSlide .25s ease;
                }

                @keyframes bookhavenWishlistSlide {
                    from {
                        transform: translateX(100%);
                    }
                    to {
                        transform: translateX(0);
                    }
                }

                #bookhavenWishlistPanel .bookhaven-wishlist-header {
                    display: flex !important;
                    align-items: center !important;
                    justify-content: space-between !important;
                    gap: 15px !important;
                    position: sticky !important;
                    top: -25px !important;
                    z-index: 2 !important;
                    padding: 20px 0 !important;
                    margin: -25px 0 15px !important;
                    background: #101d2d !important;
                    border-bottom: 1px solid #344254 !important;
                }

                #bookhavenWishlistPanel .bookhaven-wishlist-header h2 {
                    color: #e6a65d !important;
                    margin: 0 !important;
                    font-family: 'Playfair Display', Georgia, serif !important;
                    font-size: 24px !important;
                }

                #bookhavenWishlistPanel .bookhaven-wishlist-header button {
                    background: transparent !important;
                    border: 0 !important;
                    color: #fff !important;
                    font-size: 28px !important;
                    cursor: pointer !important;
                }

                #bookhavenWishlistPanel #bookhavenWishlistItems {
                    display: flex;
                    flex-direction: column;
                    gap: 0;
                }

                #bookhavenWishlistPanel .bookhaven-wishlist-item {
                    display: grid !important;
                    grid-template-columns: 92px minmax(0,1fr) !important;
                    align-items: start !important;
                    gap: 15px !important;
                    padding: 17px 0 !important;
                    border-bottom: 1px solid #344254 !important;
                }

                #bookhavenWishlistPanel .bookhaven-wishlist-cover {
                    width: 92px;
                    height: 125px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    overflow: hidden;
                    border-radius: 5px;
                    background: #192a40;
                }

                #bookhavenWishlistPanel .bookhaven-wishlist-cover img {
                    width: 100%;
                    height: 100%;
                    object-fit: contain;
                }

                #bookhavenWishlistPanel .bookhaven-wishlist-placeholder {
                    padding: 8px;
                    color: #e6a65d;
                    font-size: 11px;
                    text-align: center;
                }

                #bookhavenWishlistPanel .bookhaven-wishlist-details h3 {
                    color: #f7f3ed;
                    font-family: 'Playfair Display', Georgia, serif;
                    font-size: 16px;
                    line-height: 1.4;
                    margin: 0 0 5px;
                }

                #bookhavenWishlistPanel .bookhaven-wishlist-author,
                #bookhavenWishlistPanel .bookhaven-wishlist-stock {
                    color: #9caabd;
                    font-size: 12px;
                    margin: 5px 0;
                }

                #bookhavenWishlistPanel .bookhaven-wishlist-price {
                    display: block;
                    color: #e6a65d;
                    margin: 8px 0;
                }

                #bookhavenWishlistPanel .bookhaven-wishlist-actions {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    margin-top: 10px;
                }

                #bookhavenWishlistPanel .bookhaven-wishlist-actions button {
                    padding: 8px 11px;
                    border: 1px solid #e6a65d;
                    border-radius: 5px;
                    background: #e6a65d;
                    color: #101722;
                    font-weight: 600;
                    font-size: 11px;
                    cursor: pointer;
                }

                #bookhavenWishlistPanel .bookhaven-wishlist-actions .wish-remove-button {
                    background: transparent;
                    color: #ffaaaa;
                    border-color: #a94a4a;
                }

                #bookhavenWishlistPanel button:disabled {
                    opacity: .45;
                    cursor: not-allowed;
                }

                #bookhavenWishlistPanel .wishlist-empty {
                    padding: 35px 12px;
                    color: #9caabd;
                    line-height: 1.7;
                    text-align: center;
                }

                @media (max-width: 600px) {

                    #bookhavenWishlistPanel .bookhaven-wishlist-box {
                        width: 100% !important;
                        padding: 20px !important;
                    }

                    #bookhavenWishlistPanel .bookhaven-wishlist-item {
                        grid-template-columns: 78px minmax(0,1fr) !important;
                        gap: 12px !important;
                    }

                    #bookhavenWishlistPanel .bookhaven-wishlist-cover {
                        width: 78px;
                        height: 108px;
                    }
                }
            `;

            document.head.appendChild(style);
        }

        return panel;
    }

    const wishlistPanel = ensureWishlistPanel();

    // ==========================================
    // WISHLIST API
    // ==========================================

    async function wishlistRequest(method, payload = null) {

        const options = {
            method,
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        };

        if (payload) {
            options.headers["Content-Type"] = "application/json";
            options.body = JSON.stringify(payload);
        }

        const response = await fetch(WISHLIST_API, options);

        let result;

        try {
            result = await response.json();
        } catch (error) {
            throw new Error(
                "Wishlist API did not return valid JSON. Check api/wishlist.php."
            );
        }

        if (!response.ok || result.success !== true) {
            throw new Error(
                result.message || "Could not update the wishlist."
            );
        }

        return result.data;
    }

    function mapWishlistBook(row) {

        return {
            id: String(row.book_id ?? row.id),
            title: row.title || "Untitled book",
            author: row.author || "Unknown author",
            price: Number(row.price) || 0,
            image: imageUrl(row.cover_url || row.cover_image || ""),
            stock: Math.max(0, Number(row.stock) || 0)
        };
    }

    function renderWishlist() {

        const countElement = $("#wishlistCount");

        if (countElement) {
            countElement.textContent = String(wishlist.length);
        }

        // Update all book heart buttons.
        $$("[data-wishlist]").forEach(button => {

            const isSaved = wishlist.some(item =>
                item.id === String(button.dataset.wishlist)
            );

            button.textContent = isSaved ? "♥" : "♡";

            button.classList.toggle("selected", isSaved);

            button.setAttribute(
                "aria-label",
                isSaved ? "Remove from wishlist" : "Add to wishlist"
            );
        });

        const content = $("#bookhavenWishlistItems");

        if (!content) return;

        if (wishlistError) {
            content.innerHTML = `
                <p class="wishlist-empty">
                    ${safe(wishlistError)}
                </p>
            `;
            return;
        }

        if (wishlist.length === 0) {

            content.innerHTML = `
                <p class="wishlist-empty">
                    Your wishlist is empty.
                    Select the heart on a book to save it here.
                </p>
            `;

            return;
        }

        content.innerHTML = wishlist.map(item => `

            <article class="bookhaven-wishlist-item">

                <div class="bookhaven-wishlist-cover">

                    ${item.image
                        ? `
                            <img
                                src="${safe(item.image)}"
                                alt="${safe(item.title)}"
                                loading="lazy"
                                onerror="this.style.display='none';"
                            >
                        `
                        : `
                            <div class="bookhaven-wishlist-placeholder">
                                BOOKHAVEN
                            </div>
                        `
                    }

                </div>

                <div class="bookhaven-wishlist-details">

                    <h3>${safe(item.title)}</h3>

                    <p class="bookhaven-wishlist-author">
                        By ${safe(item.author)}
                    </p>

                    <strong class="bookhaven-wishlist-price">
                        ₹${item.price.toFixed(2)}
                    </strong>

                    <p class="bookhaven-wishlist-stock">
                        ${
                            item.stock > 0
                                ? `${item.stock} copies available`
                                : "Currently out of stock"
                        }
                    </p>

                    <div class="bookhaven-wishlist-actions">

                        <button
                            type="button"
                            data-wish-add="${safe(item.id)}"
                            ${item.stock <= 0 ? "disabled" : ""}
                        >
                            Add to Cart
                        </button>

                        <button
                            type="button"
                            class="wish-remove-button"
                            data-wish-remove="${safe(item.id)}"
                        >
                            Remove
                        </button>

                    </div>

                </div>

            </article>

        `).join("");
    }

    function loadWishlist(showErrors = true) {

        if (wishlistLoadPromise) {
            return wishlistLoadPromise;
        }

        wishlistError = "";

        const content = $("#bookhavenWishlistItems");

        if (content) {
            content.innerHTML = `
                <p class="wishlist-empty">
                    Loading your wishlist...
                </p>
            `;
        }

        wishlistLoadPromise = (async () => {

            try {

                const rows = await wishlistRequest("GET");

                wishlist = Array.isArray(rows)
                    ? rows.map(mapWishlistBook)
                    : [];

                wishlistLoaded = true;
                wishlistError = "";

            } catch (error) {

                wishlist = [];
                wishlistLoaded = false;
                wishlistError = error.message;

                if (!showErrors && /log in/i.test(error.message)) {
                    wishlistError =
                        "Please log in as a customer to view your saved wishlist.";
                }

            } finally {

                renderWishlist();

            }

        })();

        return wishlistLoadPromise.finally(() => {
            wishlistLoadPromise = null;
        });
    }

    async function toggleWishlist(book) {

        if (!book) return;

        if (!wishlistLoaded) {

            await loadWishlist(true);

            if (!wishlistLoaded) {
                toast(
                    wishlistError ||
                    "Please log in to use your wishlist."
                );
                return;
            }
        }

        const alreadySaved = wishlist.some(
            item => item.id === book.id
        );

        const action = alreadySaved ? "remove" : "add";

        try {

            await wishlistRequest("POST", {
                action,
                book_id: Number(book.id)
            });

            await loadWishlist(true);

            toast(
                alreadySaved
                    ? "Removed from wishlist."
                    : "Added to wishlist!"
            );

        } catch (error) {

            toast(error.message);

        }
    }

    async function removeWishlistItem(bookId) {

        try {

            await wishlistRequest("POST", {
                action: "remove",
                book_id: Number(bookId)
            });

            await loadWishlist(true);

            toast("Book removed from your wishlist.");

        } catch (error) {

            toast(error.message);

        }
    }

    async function openWishlist() {

        wishlistPanel.classList.add("open");

        wishlistPanel.setAttribute("aria-hidden", "false");

        document.body.style.overflow = "hidden";

        await loadWishlist(true);
    }

    function closeWishlist() {

        wishlistPanel.classList.remove("open");

        wishlistPanel.setAttribute("aria-hidden", "true");

        document.body.style.overflow = "";
    }

    // ==========================================
    // CART
    // ==========================================

    cart = cart.map(item => {

        const currentBook = getBook(item.id);

        const stock = currentBook
            ? currentBook.stock
            : Math.max(0, Number(item.stock) || 0);

        return {
            ...item,
            id: String(item.id),
            price: Number(item.price) || 0,
            stock,
            quantity: Math.max(1, Number(item.quantity) || 1)
        };

    }).filter(item => item.stock > 0)

      .map(item => ({
          ...item,
          quantity: Math.min(item.quantity, item.stock)
      }));

    function renderCart() {

        const count = cart.reduce(
            (sum, item) => sum + item.quantity,
            0
        );

        const total = cart.reduce(
            (sum, item) => sum + item.price * item.quantity,
            0
        );

        if ($("#cartCount")) {
            $("#cartCount").textContent = String(count);
        }

        if ($("#cartTotal")) {
            $("#cartTotal").textContent = "₹" + total.toFixed(2);
        }

        const content = $("#cartContent");

        if (content) {

            if (cart.length === 0) {

                content.innerHTML =
                    "<p>Your shopping bag is waiting for a story.</p>";

            } else {

                content.innerHTML = cart.map(item => `

                    <div class="cart-item">

                        <div class="cart-item-details">

                            <h4>${safe(item.title)}</h4>

                            <p>₹${item.price.toFixed(2)} each</p>

                            <p>Available stock: ${item.stock}</p>

                            <div class="bookhaven-quantity">

                                <button
                                    type="button"
                                    data-cart-minus="${safe(item.id)}"
                                    aria-label="Decrease quantity"
                                >−</button>

                                <span>${item.quantity}</span>

                                <button
                                    type="button"
                                    data-cart-plus="${safe(item.id)}"
                                    aria-label="Increase quantity"
                                    ${item.quantity >= item.stock ? "disabled" : ""}
                                >+</button>

                            </div>

                        </div>

                        <button
                            type="button"
                            data-cart-remove="${safe(item.id)}"
                        >
                            Remove
                        </button>

                    </div>

                `).join("");
            }
        }

        saveCart();
    }

    function addToCart(book) {

        if (!book) return;

        if (book.stock <= 0) {
            toast("This book is currently out of stock.");
            return;
        }

        const existing = cart.find(
            item => item.id === String(book.id)
        );

        if (existing) {

            existing.stock = book.stock;

            if (existing.quantity >= book.stock) {
                toast(
                    "Only " + book.stock + " copies are available."
                );
                return;
            }

            existing.quantity++;

        } else {

            cart.push({
                ...book,
                id: String(book.id),
                quantity: 1
            });

        }

        renderCart();

        toast(book.title + " added to your cart!");
    }

    function openCart() {
        $("#cartOverlay")?.classList.add("open");
    }

    function closeCart() {
        $("#cartOverlay")?.classList.remove("open");
    }

    // ==========================================
    // QUICK VIEW
    // ==========================================

    function openQuickView(book) {

        if (!book) return;

        const modal = $("#quickViewModal");
        const content = $("#quickViewContent");

        if (!modal || !content) {
            toast("Quick View is unavailable.");
            return;
        }

        const image = book.image
            ? `
                <img
                    src="${safe(book.image)}"
                    alt="${safe(book.title)}"
                    class="quick-view-image"
                >
            `
            : "";

        content.innerHTML = `

            ${image}

            <h2>${safe(book.title)}</h2>

            <p>${safe(book.author)}</p>

            <h3>₹${book.price.toFixed(2)}</h3>

            <p>Available stock: ${book.stock}</p>

            <button
                type="button"
                class="primary-button"
                id="quickViewAddCart"
                ${book.stock <= 0 ? "disabled" : ""}
            >
                Add to Cart
            </button>

        `;

        modal.dataset.bookId = book.id;

        modal.classList.add("open");

        modal.setAttribute("aria-hidden", "false");
    }

    function closeQuickView() {

        $("#quickViewModal")?.classList.remove("open");

        $("#quickViewModal")?.setAttribute(
            "aria-hidden",
            "true"
        );
    }

    // ==========================================
    // CLICK HANDLER
    // ==========================================

    document.addEventListener("click", async event => {

        const target = event.target;

        if (!(target instanceof Element)) return;

        const add = target.closest("[data-add-cart]");
        const wish = target.closest("[data-wishlist]");
        const quick = target.closest("[data-quick-view]");

        const plus = target.closest("[data-cart-plus]");
        const minus = target.closest("[data-cart-minus]");
        const remove = target.closest("[data-cart-remove]");

        const wishAdd = target.closest("[data-wish-add]");
        const wishRemove = target.closest("[data-wish-remove]");

        // ADD TO CART
        if (add) {
            addToCart(getBook(add.dataset.addCart));
            return;
        }

        // ADD OR REMOVE WISHLIST
        if (wish) {
            event.preventDefault();

            await toggleWishlist(
                getBook(wish.dataset.wishlist)
            );

            return;
        }

        // QUICK VIEW
        if (quick) {
            openQuickView(
                getBook(quick.dataset.quickView)
            );
            return;
        }

        // INCREASE CART QUANTITY
        if (plus) {

            const item = cart.find(
                entry => entry.id === plus.dataset.cartPlus
            );

            if (item) {

                const latest = getBook(item.id);

                const stock = latest
                    ? latest.stock
                    : item.stock;

                item.stock = stock;

                if (item.quantity >= stock) {

                    toast(
                        "Maximum available stock is " +
                        stock + "."
                    );

                } else {

                    item.quantity++;

                }
            }

            renderCart();
            return;
        }

        // DECREASE CART QUANTITY
        if (minus) {

            const item = cart.find(
                entry => entry.id === minus.dataset.cartMinus
            );

            if (item) {

                item.quantity--;

                if (item.quantity <= 0) {
                    cart = cart.filter(
                        entry => entry.id !== item.id
                    );
                }
            }

            renderCart();
            return;
        }

        // REMOVE CART ITEM
        if (remove) {

            cart = cart.filter(
                entry => entry.id !== remove.dataset.cartRemove
            );

            renderCart();

            toast("Book removed from cart.");

            return;
        }

        // ADD WISHLIST ITEM TO CART
        if (wishAdd) {

            const item = wishlist.find(
                entry => entry.id === wishAdd.dataset.wishAdd
            );

            if (item) {
                addToCart(item);
            }

            return;
        }

        // REMOVE WISHLIST ITEM
        if (wishRemove) {

            await removeWishlistItem(
                wishRemove.dataset.wishRemove
            );

            return;
        }

        // OPEN CART
        if (target.closest("#cartButton")) {
            renderCart();
            openCart();
            return;
        }

        // OPEN WISHLIST
        if (target.closest("#wishlistButton")) {
            event.preventDefault();
            await openWishlist();
            return;
        }

        // CLOSE CART
        if (target.closest("[data-close-cart]")) {
            closeCart();
            return;
        }

        // CLOSE QUICK VIEW
        if (target.closest("[data-close-modal]")) {
            closeQuickView();
            return;
        }

        // CLOSE WISHLIST
        if (target.closest("#closeBookhavenWishlist")) {
            closeWishlist();
            return;
        }

        // CLICK OUTSIDE WISHLIST
        if (target.id === "bookhavenWishlistPanel") {
            closeWishlist();
            return;
        }

        // ADD FROM QUICK VIEW
        if (target.id === "quickViewAddCart") {

            const id = $("#quickViewModal")?.dataset.bookId;

            const item = wishlist.find(
                entry => entry.id === String(id)
            );

            addToCart(item || getBook(id));

            closeQuickView();
        }

    });

    // ESCAPE KEY
    document.addEventListener("keydown", event => {

        if (event.key === "Escape") {
            closeCart();
            closeWishlist();
            closeQuickView();
        }

    });

    // HOMEPAGE REVEAL ANIMATIONS
    const revealElements = $$(".reveal");

    if ("IntersectionObserver" in window) {

        const observer = new IntersectionObserver(entries => {

            entries.forEach(entry => {

                if (entry.isIntersecting) {

                    entry.target.classList.add("visible");

                    observer.unobserve(entry.target);
                }

            });

        }, {
            threshold: 0.12
        });

        revealElements.forEach(element => {
            observer.observe(element);
        });

    } else {

        revealElements.forEach(element => {
            element.classList.add("visible");
        });

    }

    // INITIALIZE CART AND LOAD WISHLIST FROM MYSQL
    renderCart();

    loadWishlist(false);

    console.log("BookHaven cart and database wishlist ready.");

});