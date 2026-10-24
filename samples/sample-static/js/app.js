document.addEventListener('DOMContentLoaded', () => {
    let count = 0;
    const valueEl = document.getElementById('counterValue');
    const btn = document.getElementById('counterBtn');

    if (btn && valueEl) {
        btn.addEventListener('click', () => {
            count++;
            valueEl.textContent = count;
            btn.style.transform = 'scale(0.96)';
            setTimeout(() => { btn.style.transform = 'none'; }, 100);
        });
    }
});
