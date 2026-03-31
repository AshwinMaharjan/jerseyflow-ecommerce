<?php
/**
 * JerseyFlow IMS — Inventory Reports
 * File: admin/ims/ims_reports.php
 */

session_start();
require_once '../connect.php';
require_once 'ims_helpers.php';

// ── AJAX: report data ─────────────────────────────────────────
if (!empty($_GET['ajax_report'])) {
    header('Content-Type: application/json');

    $report    = $_GET['report']    ?? 'summary';
    $date_from = $_GET['date_from'] ?? date('Y-m-01');
    $date_to   = $_GET['date_to']   ?? date('Y-m-d');

    switch ($report) {

        case 'summary':
            $rows = $conn->query("
                SELECT p.product_name,
                    SUM(CASE WHEN sm.movement_type='IN'  THEN sm.quantity ELSE 0 END) AS total_in,
                    SUM(CASE WHEN sm.movement_type='OUT' THEN sm.quantity ELSE 0 END) AS total_out,
                    SUM(CASE WHEN sm.movement_type='ADJUST' THEN 1 ELSE 0 END) AS adjustments,
                    SUM(CASE WHEN sm.movement_type='DAMAGE' THEN sm.quantity ELSE 0 END) AS damaged,
                    COUNT(*) AS total_movements
                FROM stock_movements sm
                JOIN products p ON p.product_id = sm.product_id
                WHERE DATE(sm.created_at) BETWEEN ? AND ?
                GROUP BY sm.product_id ORDER BY total_out DESC
            ");
            $stmt = $conn->prepare("
                SELECT p.product_name,
                    SUM(CASE WHEN sm.movement_type='IN'  THEN sm.quantity ELSE 0 END) AS total_in,
                    SUM(CASE WHEN sm.movement_type='OUT' THEN sm.quantity ELSE 0 END) AS total_out,
                    SUM(CASE WHEN sm.movement_type='ADJUST' THEN 1 ELSE 0 END) AS adjustments,
                    SUM(CASE WHEN sm.movement_type='DAMAGE' THEN sm.quantity ELSE 0 END) AS damaged,
                    COUNT(*) AS total_movements
                FROM stock_movements sm
                JOIN products p ON p.product_id = sm.product_id
                WHERE DATE(sm.created_at) BETWEEN ? AND ?
                GROUP BY sm.product_id ORDER BY total_out DESC
            ");
            $stmt->bind_param('ss', $date_from, $date_to);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            break;

        case 'valuation':
            $stmt = $conn->prepare("
                SELECT p.product_name, pv.size, pv.color, pv.sku, pv.stock, pv.cost_price,
                       (pv.stock * COALESCE(pv.cost_price,0)) AS total_value
                FROM product_variants pv
                JOIN products p ON p.product_id = pv.product_id
                WHERE pv.is_active = 1
                ORDER BY total_value DESC
            ");
            $stmt->execute();
            $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            break;

        case 'dead_stock':
            $days = (int)($_GET['days'] ?? 90);
            $data = ims_dead_stock($conn, $days);
            break;

        case 'by_type':
            $stmt = $conn->prepare("
                SELECT movement_type,
                    COUNT(*) AS count,
                    SUM(quantity) AS total_qty,
                    DATE_FORMAT(created_at,'%b %Y') AS month_label
                FROM stock_movements
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY movement_type, month_label
                ORDER BY created_at ASC
            ");
            $stmt->bind_param('ss', $date_from, $date_to);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            break;

        default:
            $data = [];
    }

    echo json_encode($data);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Inventory Reports — JerseyFlow IMS</title>
    <link rel="icon" href="../../images/logo_icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../../style/footer.css">
    <link rel="stylesheet" href="../../style/admin_menu.css">
    <link rel="stylesheet" href="../../style/admin_navbar.css">
    <link rel="stylesheet" href="../../style/all_products.css">
    <link rel="stylesheet" href="../../style/ims.css">

</head>
<body>
<?php include '../admin_navbar.php'; ?>

<div class="page-wrapper">
    <?php include '../admin_menu.php'; ?>
    <div class="main-content">

  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa-solid fa-chart-bar"></i> Inventory Reports</h1>
      <p class="page-subtitle">Analyze stock movements, valuation, and trends</p>
    </div>
    <button onclick="exportCSV()" class="btn-ims-ghost"><i class="fa-solid fa-file-csv"></i> Export CSV</button>
  </div>

  <!-- Report Controls -->
  <div class="ims-filter-bar ims-filter-bar-extended">
    <select id="reportType" class="ims-select">
      <option value="summary">Movement Summary</option>
      <option value="valuation">Stock Valuation</option>
      <option value="dead_stock">Dead Stock</option>
      <option value="by_type">By Movement Type</option>
    </select>
    <label class="filter-date-label">From</label>
    <input type="date" id="dateFrom" class="ims-input-date" value="<?= date('Y-m-01') ?>" />
    <label class="filter-date-label">To</label>
    <input type="date" id="dateTo"   class="ims-input-date" value="<?= date('Y-m-d')  ?>" />
    <div id="deadDaysWrap" style="display:none; align-items:center; gap:8px;">
      <label class="filter-date-label">Days</label>
      <select id="deadDays" class="ims-select">
        <option value="30">30 days</option>
        <option value="60">60 days</option>
        <option value="90" selected>90 days</option>
      </select>
    </div>
    <button onclick="runReport()" class="btn-ims-primary"><i class="fa-solid fa-play"></i> Run Report</button>
    <span class="result-count" id="resultCount"></span>
  </div>

  <!-- Report Output -->
  <div class="ims-card" style="margin-top:0;">
    <div id="reportOutput">
      <div class="ims-empty" style="padding:60px 0;">
        <i class="fa-solid fa-chart-bar" style="font-size:40px;opacity:.25;"></i>
        <p>Select a report type and click Run Report.</p>
      </div>
    </div>
  </div>
  
</main>
</div>
</div>
</div>

<?php include '../footer.php'; ?>

<script src="../../script/admin_menu.js"></script>

<script>
(function(){

  const COLS = {
    summary: [
      { key:'product_name',     label:'Product'      },
      { key:'total_in',         label:'Stock In'     },
      { key:'total_out',        label:'Stock Out'    },
      { key:'damaged',          label:'Damaged'      },
      { key:'adjustments',      label:'Adjustments'  },
      { key:'total_movements',  label:'Total Moves'  },
    ],
    valuation: [
      { key:'product_name',  label:'Product'      },
      { key:'size',          label:'Size'         },
      { key:'color',         label:'Color'        },
      { key:'sku',           label:'SKU'          },
      { key:'stock',         label:'Stock'        },
      { key:'cost_price',    label:'Unit Cost'    },
      { key:'total_value',   label:'Total Value'  },
    ],
    dead_stock: [
      { key:'product_name',  label:'Product' },
      { key:'size',          label:'Size'    },
      { key:'color',         label:'Color'   },
      { key:'stock',         label:'Stock'   },
    ],
    by_type: [
      { key:'movement_type', label:'Type'       },
      { key:'month_label',   label:'Month'      },
      { key:'count',         label:'Count'      },
      { key:'total_qty',     label:'Total Qty'  },
    ],
  };

  let currentData  = [];
  let currentReport = 'summary';

  document.getElementById('reportType').addEventListener('change', function() {
    const isDead = this.value === 'dead_stock';
    document.getElementById('deadDaysWrap').style.display  = isDead ? 'flex' : 'none';
    const needsDates = this.value !== 'valuation' && this.value !== 'dead_stock';
    document.getElementById('dateFrom').closest('label') && null;
  });

  window.runReport = function() {
    const report   = document.getElementById('reportType').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo   = document.getElementById('dateTo').value;
    const days     = document.getElementById('deadDays').value;
    currentReport  = report;

    const output = document.getElementById('reportOutput');
    output.innerHTML = '<div class="ims-empty" style="padding:48px 0;"><i class="fa-solid fa-spinner fa-spin" style="font-size:24px;"></i><p>Generating report…</p></div>';

    const params = `ajax_report=1&report=${report}&date_from=${dateFrom}&date_to=${dateTo}&days=${days}`;
    fetch(`ims_reports.php?${params}`)
      .then(r => r.json())
      .then(data => {
        currentData = data;
        document.getElementById('resultCount').textContent = data.length + ' rows';
        renderTable(data, COLS[report] || []);
      })
      .catch(() => { output.innerHTML = '<div class="ims-empty"><p>Failed to load report.</p></div>'; });
  };

  function renderTable(data, cols) {
    const output = document.getElementById('reportOutput');
    if (!data.length) {
      output.innerHTML = '<div class="ims-empty" style="padding:48px 0;"><i class="fa-solid fa-inbox" style="font-size:32px;opacity:.25;"></i><p>No data for selected period.</p></div>';
      return;
    }

    // Totals row for numeric cols
    const totals = {};
    cols.forEach(c => {
      const nums = data.map(r => parseFloat(r[c.key])||0);
      if (nums.some(n=>n>0)) totals[c.key] = nums.reduce((a,b)=>a+b,0);
    });

    const thead = `<tr>${cols.map(c=>`<th>${c.label}</th>`).join('')}</tr>`;
    const tbody = data.map(row => `<tr>${cols.map(c => {
      let val = row[c.key] ?? '—';
      if (c.key==='total_value' || c.key==='cost_price') val = val!=='—' ? 'Rs.'+parseFloat(val).toFixed(2) : '—';
      if (c.key==='movement_type') val = `<span class="ims-badge ims-badge-${val.toLowerCase()}">${val}</span>`;
      return `<td>${escHtml(String(val))}</td>`;
    }).join('')}</tr>`).join('');

    const tfoot = `<tr class="totals-row">${cols.map(c=>{
      const v = totals[c.key];
      if (v===undefined) return `<td>—</td>`;
      if (c.key==='total_value'||c.key==='cost_price') return `<td>Rs.${v.toFixed(2)}</td>`;
      return `<td>${v.toLocaleString()}</td>`;
    }).join('')}</tr>`;

    output.innerHTML = `
      <div class="ims-table-wrap">
        <table class="ims-table report-table">
          <thead>${thead}</thead>
          <tbody>${tbody}</tbody>
          <tfoot>${tfoot}</tfoot>
        </table>
      </div>`;
  }

  window.exportCSV = function() {
    if (!currentData.length) { alert('Run a report first.'); return; }
    const cols = COLS[currentReport] || [];
    const header = cols.map(c=>c.label).join(',');
    const rows   = currentData.map(r => cols.map(c => `"${String(r[c.key]??'').replace(/"/g,'""')}"`).join(','));
    const csv    = [header, ...rows].join('\n');
    const a      = document.createElement('a');
    a.href       = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    a.download   = `ims_${currentReport}_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
  };

  function escHtml(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

  // Auto-run on load
  runReport();
})();
</script>
</body>
</html>