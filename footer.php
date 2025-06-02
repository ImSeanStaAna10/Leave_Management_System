    </div> <!-- End main-content -->

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        // Initialize tooltips
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

        // Tab navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Initialize charts
        function initCharts() {
            // Leave Status Chart
            const leaveCtx = document.getElementById('leaveStatusChart').getContext('2d');
            new Chart(leaveCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Approved', 'Pending', 'Rejected'],
                    datasets: [{
                        data: [<?= $approved_count ?>, <?= $pending_count ?>, <?= $rejected_count ?>],
                        backgroundColor: [
                            'rgba(40, 167, 69, 0.8)',
                            'rgba(255, 193, 7, 0.8)',
                            'rgba(220, 53, 69, 0.8)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Monthly Leaves Chart
            const monthlyCtx = document.getElementById('monthlyLeavesChart').getContext('2d');
            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Leaves Taken',
                        data: [12, 19, 15, 17, 14, 13, 10, 15, 18, 16, 14, 20],
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Export functions
        function exportCSV() {
            alert('CSV export functionality would be implemented here');
            // Actual implementation would generate CSV file
        }

        function exportPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            doc.text('Leave Management Report', 20, 20);
            doc.text(`Date: ${new Date().toLocaleDateString()}`, 20, 30);
            doc.text(`Total Employees: ${<?= $employees_count ?>}`, 20, 40);
            doc.text(`Approved Leaves: ${<?= $approved_count ?>}`, 20, 50);
            doc.text(`Pending Leaves: ${<?= $pending_count ?>}`, 20, 60);
            doc.text(`Rejected Leaves: ${<?= $rejected_count ?>}`, 20, 70);
            
            doc.save('leave-report.pdf');
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
            
            // Filter functionality
            document.getElementById('employeeFilter').addEventListener('input', filterEmployees);
            document.getElementById('leaveFilter').addEventListener('input', filterLeaves);
        });

        function filterEmployees() {
            const filter = document.getElementById('employeeFilter').value.toLowerCase();
            document.querySelectorAll('.employee-card').forEach(card => {
                const name = card.querySelector('.card-title').textContent.toLowerCase();
                card.style.display = name.includes(filter) ? '' : 'none';
            });
        }

        function filterLeaves() {
            const filter = document.getElementById('leaveFilter').value.toLowerCase();
            document.querySelectorAll('.leave-box').forEach(box => {
                const name = box.querySelector('h5').textContent.toLowerCase();
                box.style.display = name.includes(filter) ? '' : 'none';
            });
        }

        // Show reason popup
        function showReasonPopup(text) {
            document.getElementById('reasonText').textContent = text;
            const modal = new bootstrap.Modal(document.getElementById('reasonModal'));
            modal.show();
        }
    </script>
</body>
</html>