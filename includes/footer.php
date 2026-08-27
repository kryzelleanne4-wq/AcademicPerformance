<?php
/**
 * Common Footer
 */
?>
        </div><!-- /.page-content -->
    </div><!-- /.main-content -->
    
    <script src="<?php echo $assetPrefix ?? '../'; ?>assets/js/main.js"></script>
    <script>
        // Add lightweight pagination to every table with a data-pagination attribute.
        document.querySelectorAll('table[data-pagination]').forEach(function (table) {
            var rows = Array.from(table.querySelectorAll('tbody tr'));
            var pageSize = parseInt(table.dataset.pageSize || '10', 10);
            var page = 1;
            var totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
            var wrapper = table.closest('.table-container') || table.parentElement;
            var controls = document.createElement('div');
            controls.className = 'pagination';
            wrapper.appendChild(controls);

            function render() {
                totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
                page = Math.min(page, totalPages);
                rows.forEach(function (row, index) {
                    row.hidden = index < (page - 1) * pageSize || index >= page * pageSize;
                });
                controls.innerHTML = '';
                if (rows.length <= pageSize) return;

                var previous = document.createElement('button');
                previous.type = 'button';
                previous.className = 'pagination-btn';
                previous.textContent = 'Previous';
                previous.disabled = page === 1;
                previous.addEventListener('click', function () { page--; render(); });
                controls.appendChild(previous);

                var status = document.createElement('span');
                status.className = 'pagination-status';
                status.textContent = 'Page ' + page + ' of ' + totalPages;
                controls.appendChild(status);

                var next = document.createElement('button');
                next.type = 'button';
                next.className = 'pagination-btn';
                next.textContent = 'Next';
                next.disabled = page === totalPages;
                next.addEventListener('click', function () { page++; render(); });
                controls.appendChild(next);
            }
            render();
        });
    </script>
</body>
</html>
