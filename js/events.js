
// Optional: Simple horizontal scroll for gallery (drag to scroll)
document.addEventListener("DOMContentLoaded",function(){
    document.querySelectorAll('.event-gallery-slider').forEach(function(slider){
        let isDown=false, startX, scrollLeft;
        slider.addEventListener('mousedown', function(e) {
            isDown=true;
            slider.classList.add('active');
            startX = e.pageX - slider.offsetLeft;
            scrollLeft = slider.scrollLeft;
        });
        slider.addEventListener('mouseleave', ()=>{ isDown=false; slider.classList.remove('active'); });
        slider.addEventListener('mouseup', ()=>{ isDown=false; slider.classList.remove('active'); });
        slider.addEventListener('mousemove', function(e){
            if(!isDown) return;
            e.preventDefault();
            const x = e.pageX - slider.offsetLeft;
            const walk = (x - startX)*1.3;
            slider.scrollLeft = scrollLeft - walk;
        });
    });
});
