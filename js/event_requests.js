(function() {
    var imagesModal = document.getElementById("images-manage-modal");
    var openBtn = document.getElementById("open-images-modal");
    var closeBtn = document.getElementById("close-images-modal");
    if (imagesModal && openBtn && closeBtn) {
        openBtn.addEventListener('click', function(e){
            imagesModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
        closeBtn.addEventListener('click', function(){
            imagesModal.style.display = 'none';
            document.body.style.overflow = '';
        });
        imagesModal.addEventListener('click', function(ev){
            if (ev.target === imagesModal) {
                imagesModal.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
    }
})();
