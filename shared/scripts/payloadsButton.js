    (function () {
      const toggleBtn = document.getElementById('toggleAllPayloads');
      const bodies = document.querySelectorAll('.payload-body');

      let shown = false;

      toggleBtn.addEventListener('click', () => {
        shown = !shown;

        bodies.forEach(el => {
          el.style.display = shown ? 'block' : 'none';
        });

        toggleBtn.textContent = shown ? 'Hide all' : 'Show all';
      });
    })();
