document.getElementById("contactForm").addEventListener("submit", function(e) {
    e.preventDefault();
    alert("Gracias por contactarnos. Pronto nos comunicaremos contigo.");
    this.reset();
});
