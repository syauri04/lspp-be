$(document).ready(function () {
    $(".datatable").each(function () {
        if ($.fn.DataTable.isDataTable(this)) {
            return; // sudah di-init
        }

        let tableEl = $(this);
        let table = tableEl.DataTable({
            responsive: true,
            columnDefs: [
                {
                    targets: tableEl.find("th.dt-no").index(),
                    orderable: false,
                    searchable: false,
                },
                {
                    targets: tableEl.find("th.dt-action").index(),
                    orderable: false,
                    searchable: false,
                },
            ],
        });

        function updateRowNumbers() {
            let info = table.page.info();
            let noIndex = tableEl.find("th.dt-no").index();

            table
                .column(noIndex, { page: "current" })
                .nodes()
                .each(function (cell, i) {
                    cell.innerHTML = info.start + i + 1;
                });
        }

        table.on("draw.dt", updateRowNumbers);
        updateRowNumbers();
    });

    $(".dataTables_length select").addClass("form-select form-select-sm");
});
