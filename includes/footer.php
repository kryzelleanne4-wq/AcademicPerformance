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
            var allRows = Array.from(table.querySelectorAll('tbody tr'));
            var pageSize = parseInt(table.dataset.pageSize || '10', 10);
            var page = 1;
            var wrapper = table.closest('.table-container') || table.parentElement;
            var controls = document.createElement('div');
            controls.className = 'pagination';
            wrapper.appendChild(controls);

            function getVisibleRows() {
                return allRows.filter(function(row) {
                    return row.dataset.searchHidden !== '1';
                });
            }

            function render() {
                var visibleRows = getVisibleRows();
                var totalPages = Math.max(1, Math.ceil(visibleRows.length / pageSize));
                page = Math.min(page, totalPages);

                // Hide all rows first
                allRows.forEach(function (row) {
                    row.hidden = true;
                });
                // Show only the visible (non-search-hidden) rows on the current page
                visibleRows.forEach(function (row, index) {
                    row.hidden = index < (page - 1) * pageSize || index >= page * pageSize;
                });

                controls.innerHTML = '';
                if (visibleRows.length <= pageSize) return;

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

            // Re-render pagination when search filter changes
            var searchInput = wrapper.querySelector('.table-search-bar input[type="text"]');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    // Use setTimeout to let the search filter apply first
                    setTimeout(function() { page = 1; render(); }, 10);
                });
            }

            render();
        });
    </script>
</body>
</html>
