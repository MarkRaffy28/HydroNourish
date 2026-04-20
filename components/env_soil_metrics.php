<div class="page-section">
  <div class="section-head">
    <div class="flex-between w-100">
      <div class="flex-center-j-center gap-5">
        <div class="section-icon green"><i class="fas fa-satellite-dish"></i></div>
        <div class="section-label ">Environment & Soil</div>
      </div>
      <?php if (isset($show_meta) && $show_meta): ?>
      <div class="connection-status-badge online">
        <span class="connection-status-dot online"></span>
        Updates every 5s
      </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="grid-auto">
    <!-- Soil Moisture -->
    <div class="stat-card delay-1" id="card-soil">
      <div class="stat-top">
        <div class="stat-icon-wrap"><i class="fas fa-droplet"></i></div>
        <span class="stat-badge">--</span>
      </div>
      <div class="stat-value" id="val-soil">--<span class="stat-unit"> %</span></div>
      <div class="stat-label">Soil Moisture</div>
      <div class="sub-label-muted" id="sub-soil">—</div>
    </div>
    
    <!-- Temperature -->
    <div class="stat-card delay-2" id="card-temp">
      <div class="stat-top">
        <div class="stat-icon-wrap"><i class="fas fa-temperature-high"></i></div>
        <span class="stat-badge">--</span>
      </div>
      <div class="stat-value" id="val-temp">--<span class="stat-unit"> °C</span></div>
      <div class="stat-label">Temperature</div>
      <div class="sub-label-muted" id="sub-temp">—</div>
    </div>
    
    <!-- Humidity -->
    <div class="stat-card delay-3" id="card-hum">
      <div class="stat-top">
        <div class="stat-icon-wrap"><i class="fas fa-cloud-rain"></i></div>
        <span class="stat-badge">--</span>
      </div>
      <div class="stat-value" id="val-hum">--<span class="stat-unit"> %</span></div>
      <div class="stat-label">Humidity</div>
      <div class="sub-label-muted" id="sub-hum">—</div>
    </div>
    
    <!-- Pressure -->
    <div class="stat-card delay-4" id="card-pres">
      <div class="stat-top">
        <div class="stat-icon-wrap"><i class="fas fa-wind"></i></div>
        <span class="stat-badge">--</span>
      </div>
      <div class="stat-value" id="val-pres">--<span class="stat-unit"> hPa</span></div>
      <div class="stat-label">Pressure</div>
      <div class="sub-label-muted" id="sub-pres">—</div>
    </div>
  </div>
</div>
