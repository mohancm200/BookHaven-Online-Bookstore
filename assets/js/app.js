
document.addEventListener("DOMContentLoaded", () => {
    "use strict";

    const CART_KEY = "bookhaven_cart";
    const WISH_KEY = "bookhaven_wishlist";

    const $ = (s, p = document) => p.querySelector(s);
    const $$ = (s, p = document) => [...p.querySelectorAll(s)];

    function load(key) {
        try {
            const data = JSON.parse(localStorage.getItem(key));
            return Array.isArray(data) ? data : [];
        } catch {
            return [];
        }
    }

    let cart = load(CART_KEY);
    let wishlist = load(WISH_KEY);

    cart = cart.map(item => ({
        ...item,
        id: String(item.id),
        price: Number(item.price) || 0,
        stock: Number(item.stock) || 0,
        quantity: Math.max(1, Number(item.quantity) || 1)
    }));

    cart = cart
        .filter(item => item.stock > 0)
        .map(item => ({
            ...item,
            quantity: Math.min(item.quantity, item.stock)
        }));

    wishlist = wishlist.map(item => ({
        ...item,
        id: String(item.id),
        price: Number(item.price) || 0
    }));

    function save() {
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
        localStorage.setItem(WISH_KEY, JSON.stringify(wishlist));
    }

    function safe(value) {
        return String(value ?? "").replace(/[&<>"']/g, c => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        })[c]);
    }

    function toast(message) {
        const element = $("#toast");

        if (!element) {
            alert(message);
            return;
        }

        element.textContent = message;
        element.classList.add("show");

        clearTimeout(toast.timer);
        toast.timer = setTimeout(
            () => element.classList.remove("show"),
            2500
        );
    }

    // GET BOOK DETAILS
    function getBook(id) {
        const card = $$(".product-card").find(
            c => String(c.dataset.bookId) === String(id)
        );

        if (!card) {
            console.error("Book card not found:", id);
            return null;
        }

        const title = $(".product-details h3", card);
        const author = $(".product-details p", card);
        const price = $(".product-bottom strong", card);
        const image = $(".product-cover img", card);

        if (!title || !price) {
            console.error("Book details missing:", id);
            return null;
        }

        return {
            id: String(id),
            title: title.textContent.trim(),
            author: author?.textContent.trim() || "Unknown author",
            price: Number(price.textContent.replace(/[^\d.]/g, "")) || 0,
            image: image?.getAttribute("src") || "",

            // Homepage cards do not currently provide data-stock.
            // Use a fallback until actual stock is supplied by the page.
            stock: card.dataset.stock !== undefined
                ? Math.max(0, Number(card.dataset.stock) || 0)
                : 999
        };
    }

    // CART
    function renderCart() {
        const count = cart.reduce(
            (sum, item) => sum + item.quantity, 0
        );

        const total = cart.reduce(
            (sum, item) => sum + item.price * item.quantity, 0
        );

        if ($("#cartCount")) {
            $("#cartCount").textContent = count;
        }

        if ($("#cartTotal")) {
            $("#cartTotal").textContent = "₹" + total.toFixed(2);
        }

        const content = $("#cartContent");

        if (content) {
            if (!cart.length) {
                content.innerHTML =
                    "<p>Your shopping bag is waiting for a story.</p>";
            } else {
                content.innerHTML = cart.map(item => `
                    <div class="cart-item">
                        <div>
                            <h4>${safe(item.title)}</h4>
                            <p>₹${item.price.toFixed(2)} each</p>
                            <p>${item.stock >= 999
                                ? "Stock availability confirmed at checkout"
                                : `Available stock: ${item.stock}`}</p>

                            <div class="bookhaven-quantity">
                                <button type="button"
                                    data-cart-minus="${safe(item.id)}">−</button>

                                <span>${item.quantity}</span>

                                <button type="button"
                                    data-cart-plus="${safe(item.id)}"
                                    ${item.quantity >= item.stock ? "disabled" : ""}>+</button>
                            </div>
                        </div>

                        <button type="button"
                            data-cart-remove="${safe(item.id)}">
                            Remove
                        </button>
                    </div>
                `).join("");
            }
        }

        save();
    }

    function addToCart(book) {
        if (!book) return;

        if (book.stock <= 0) {
            toast("This book is currently out of stock.");
            return;
        }

        const existing = cart.find(item => item.id === book.id);

        if (existing) {
            if (existing.quantity >= book.stock) {
                toast("Only " + book.stock + " copies are available.");
                return;
            }

            existing.quantity++;
            existing.stock = book.stock;
        } else {
            cart.push({
                ...book,
                quantity: 1
            });
        }

        renderCart();
        toast(book.title + " added to your cart!");
    }

    // WISHLIST
    function renderWishlist() {
        if ($("#wishlistCount")) {
            $("#wishlistCount").textContent = wishlist.length;
        }

        $$("[data-wishlist]").forEach(button => {
            const selected = wishlist.some(
                item => item.id === String(button.dataset.wishlist)
            );

            button.textContent = selected ? "♥" : "♡";
            button.classList.toggle("selected", selected);
        });

        const panel = $("#bookhavenWishlistItems");

        if (panel) {
            panel.innerHTML = wishlist.length
                ? wishlist.map(item => `
                    <div class="bookhaven-wishlist-item">
                        <div>
                            <strong>${safe(item.title)}</strong>
                            <p>₹${item.price.toFixed(2)}</p>
                        </div>

                        <div>
                            <button type="button"
                                data-wish-add="${safe(item.id)}">
                                Add to Cart
                            </button>

                            <button type="button"
                                data-wish-remove="${safe(item.id)}">
                                Remove
                            </button>
                        </div>
                    </div>
                `).join("")
                : "<p>Your wishlist is empty.</p>";
        }

        save();
    }

    function toggleWishlist(book) {
        if (!book) return;

        const index = wishlist.findIndex(
            item => item.id === book.id
        );

        if (index >= 0) {
            wishlist.splice(index, 1);
            toast("Removed from wishlist.");
        } else {
            wishlist.push(book);
            toast("Added to wishlist!");
        }

        renderWishlist();
    }

    // OVERLAYS
    function openCart() {
        $("#cartOverlay")?.classList.add("open");
    }

    function closeCart() {
        $("#cartOverlay")?.classList.remove("open");
    }

    function openWishlist() {
        renderWishlist();
        $("#bookhavenWishlistPanel")?.classList.add("open");
    }

    function closeWishlist() {
        $("#bookhavenWishlistPanel")?.classList.remove("open");
    }

    function closeQuickView() {
        $("#quickViewModal")?.classList.remove("open");
        $("#quickViewModal")?.setAttribute("aria-hidden", "true");
    }

    // CREATE WISHLIST PANEL
    if (!$("#bookhavenWishlistPanel")) {
        const panel = document.createElement("div");
        panel.id = "bookhavenWishlistPanel";

        panel.innerHTML = `
            <div class="bookhaven-wishlist-box">
                <div class="bookhaven-wishlist-header">
                    <h2>Your Wishlist ♥</h2>

                    <button type="button"
                        id="closeBookhavenWishlist">✕</button>
                </div>

                <div id="bookhavenWishlistItems"></div>
            </div>
        `;

        document.body.appendChild(panel);
    }

    // QUICK VIEW
    function openQuickView(book) {
        if (!book) return;

        const modal = $("#quickViewModal");
        const content = $("#quickViewContent");

        if (!modal || !content) {
            toast("Quick View is unavailable.");
            return;
        }

        const image = book.image
            ? `<img src="${safe(book.image)}"
                    alt="${safe(book.title)}"
                    style="display:block;max-width:180px;max-height:230px;object-fit:contain;margin:0 auto 20px">`
            : "";

        content.innerHTML = `
            ${image}
            <h2>${safe(book.title)}</h2>
            <p>${safe(book.author)}</p>
            <h3>₹${book.price.toFixed(2)}</h3>
            <p>Available stock: ${book.stock}</p>

            <button type="button"
                class="primary-button"
                id="quickViewAddCart"
                ${book.stock <= 0 ? "disabled" : ""}>
                Add to Cart
            </button>
        `;

        modal.dataset.bookId = book.id;
        modal.classList.add("open");
        modal.setAttribute("aria-hidden", "false");
    }

    // ALL BUTTON CLICKS
    document.addEventListener("click", event => {
        const add = event.target.closest("[data-add-cart]");
        const wish = event.target.closest("[data-wishlist]");
        const quick = event.target.closest("[data-quick-view]");

        const plus = event.target.closest("[data-cart-plus]");
        const minus = event.target.closest("[data-cart-minus]");
        const remove = event.target.closest("[data-cart-remove]");

        const wishAdd = event.target.closest("[data-wish-add]");
        const wishRemove = event.target.closest("[data-wish-remove]");

        if (add) {
            addToCart(getBook(add.dataset.addCart));
            return;
        }

        if (wish) {
            toggleWishlist(getBook(wish.dataset.wishlist));
            return;
        }

        if (quick) {
            openQuickView(getBook(quick.dataset.quickView));
            return;
        }

        if (plus) {
            const item = cart.find(
                i => i.id === plus.dataset.cartPlus
            );

            if (item) {
                const currentBook = getBook(item.id);
                const latestStock = currentBook?.stock ?? item.stock;

                item.stock = latestStock;

                if (item.quantity >= latestStock) {
                    toast("Maximum available stock is " + latestStock + ".");
                } else {
                    item.quantity++;
                }
            }

            renderCart();
            return;
        }

        if (minus) {
            const item = cart.find(
                i => i.id === minus.dataset.cartMinus
            );

            if (item) {
                item.quantity--;

                if (item.quantity <= 0) {
                    cart = cart.filter(i => i.id !== item.id);
                }
            }

            renderCart();
            return;
        }

        if (remove) {
            cart = cart.filter(
                i => i.id !== remove.dataset.cartRemove
            );

            renderCart();
            toast("Book removed from cart.");
            return;
        }

        if (wishAdd) {
            const item = wishlist.find(
                i => i.id === wishAdd.dataset.wishAdd
            );

            if (item) {
                addToCart(getBook(item.id) || item);
            }

            return;
        }

        if (wishRemove) {
            wishlist = wishlist.filter(
                i => i.id !== wishRemove.dataset.wishRemove
            );

            renderWishlist();
            return;
        }

        if (event.target.closest("#cartButton")) {
            renderCart();
            openCart();
            return;
        }

        if (event.target.closest("#wishlistButton")) {
            openWishlist();
            return;
        }

        if (event.target.closest("[data-close-cart]")) {
            closeCart();
            return;
        }

        if (event.target.closest("[data-close-modal]")) {
            closeQuickView();
            return;
        }

        if (event.target.closest("#closeBookhavenWishlist")) {
            closeWishlist();
            return;
        }

        if (event.target.id === "quickViewAddCart") {
            const id = $("#quickViewModal")?.dataset.bookId;
            addToCart(getBook(id));
            closeQuickView();
            return;
        }

        if (event.target.id === "cartOverlay") {
            closeCart();
        }

        if (event.target.id === "bookhavenWishlistPanel") {
            closeWishlist();
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

    // INITIALIZE
    renderCart();
    renderWishlist();

    console.log("BookHaven cart and wishlist ready.");
});

// ==========================================
// RESTORE ORIGINAL HOMEPAGE REVEAL ANIMATIONS
// ==========================================

document.addEventListener("DOMContentLoaded", () => {
    const revealElements = document.querySelectorAll(".reveal");

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
});
