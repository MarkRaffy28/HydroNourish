/**
 * HydroNourish Dashboard Logic
 * Handles real-time sensor updates for the main dashboard view.
 */

const Dashboard = {
    // ── Configuration ──
    refreshInterval: 5000, 
    apiUrl: 'dashboard.php?ajax=1',
    
    // ── Initialization ──
    init() {
        this.updateLatest();
        setInterval(() => this.updateLatest(), this.refreshInterval);
        
        // Initial activity load
        if (typeof window.loadRecentActivities === 'function') {
            window.loadRecentActivities();
        }
    },

    // ── Fetching Data ──
    updateLatest() {
        $.getJSON(`${this.apiUrl}&type=latest&_=${Date.now()}`)
            .done(data => {
                if (data && data.id) {
                    this.renderSensorData(data);
                    this.updateErrorBanner(data);
                }
            })
            .fail(error => console.error('Dashboard Update Error:', error));
    },

    // ── Rendering Sensor Metrics ──
    renderSensorData(d) {
        EnvSoilUI.updateAll(d);
        
        // Tank Levels (Still specific to dashboard's unique tank layout)
        this.updateTank($('#water-pct'), $('#water-bar'), d.water_level);
        this.updateTank($('#fert-pct'),  $('#fert-bar'),  d.fert_level);

        // Tank Distances (Explicit check for 0/null)
        const wDist = (d.water_distance !== null && d.water_distance !== undefined) ? d.water_distance + ' cm' : '-- cm';
        const fDist = (d.fert_distance !== null && d.fert_distance !== undefined) ? d.fert_distance + ' cm' : '-- cm';
        $('#water-dist').text(wDist);
        $('#fert-dist').text(fDist);
    },

    // ── Helper: Update Tank UI ──
    updateTank($pctEl, $barEl, val) {
        $pctEl.text(`${val}%`);
        $barEl.css('height', `${val}%`);
        
        // If low level, show red warning liquid
        if (val <= 20) {
            $barEl.css('background', 'linear-gradient(180deg, #ff4d4d 0%, #b30000 100%)');
        } else {
            $barEl.css('background', ''); // Use default from CSS
        }
    },

    // ── Error Banner UI ──
    updateErrorBanner(d) {
        EnvSoilUI.updateErrorBanner(d);
    }
};

// Start the dashboard
$(function() {
    Dashboard.init();
});
