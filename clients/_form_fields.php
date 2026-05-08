<?php
/** Shared form fields for clients/add.php and clients/edit.php */
$default = [
    'id'=>0,'name'=>'','type'=>'pharmacy','license_no'=>'','kra_pin'=>'','contact_person'=>'',
    'contact_role'=>'','phone'=>'','email'=>'','address'=>'','postal_address'=>'',
    'region'=>'','city'=>'','lat'=>'','lng'=>'','kyc_status'=>'pending','kyc_notes'=>'',
    'rep_id'=>'','credit_limit'=>0,'notes'=>'',
    'directors'=>'','accountant_name'=>'','accountant_phone'=>'',
    'bank_name'=>'','bank_branch'=>'','payment_terms'=>'30 days from invoice','credit_period_days'=>30,
    'trade_ref_1'=>'','trade_ref_2'=>'','trade_ref_3'=>'',
    'signed_name'=>'','signed_position'=>'','signed_at'=>'',
];
$row  = array_merge($default, $row ?? []);
$reps = $reps ?? [];
$u    = current_user();
?>
<?= csrf_field() ?>

<h6 class="text-muted text-uppercase small mt-1 mb-2">Business identity</h6>
<div class="row g-3">
  <div class="col-md-6"><label class="form-label">Business / company name *</label>
    <input class="form-control" name="name" required value="<?= e($row['name']) ?>"></div>
  <div class="col-md-3"><label class="form-label">Type</label>
    <select class="form-select" name="type">
      <?php foreach (['pharmacy'=>'Pharmacy','clinic'=>'Clinic','hospital'=>'Hospital','wholesaler'=>'Wholesaler','other'=>'Other'] as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $row['type']===$k?'selected':'' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="col-md-3"><label class="form-label">PPB / KMPDC licence #</label>
    <input class="form-control" name="license_no" value="<?= e($row['license_no']) ?>"></div>

  <div class="col-md-3"><label class="form-label">KRA PIN</label>
    <input class="form-control" name="kra_pin" value="<?= e($row['kra_pin']) ?>"></div>
  <div class="col-md-3"><label class="form-label">Contact person</label>
    <input class="form-control" name="contact_person" value="<?= e($row['contact_person']) ?>"></div>
  <div class="col-md-3"><label class="form-label">Role / position</label>
    <input class="form-control" name="contact_role" value="<?= e($row['contact_role']) ?>"></div>
  <div class="col-md-3"><label class="form-label">Phone</label>
    <input class="form-control" name="phone" value="<?= e($row['phone']) ?>"></div>

  <div class="col-md-6"><label class="form-label">Email</label>
    <input class="form-control" type="email" name="email" value="<?= e($row['email']) ?>"></div>
  <div class="col-md-3"><label class="form-label">Region / county</label>
    <input class="form-control" name="region" value="<?= e($row['region']) ?>"></div>
  <div class="col-md-3"><label class="form-label">City / town</label>
    <input class="form-control" name="city" value="<?= e($row['city']) ?>"></div>

  <div class="col-md-6"><label class="form-label">Physical address</label>
    <input class="form-control" name="address" value="<?= e($row['address']) ?>"></div>
  <div class="col-md-6"><label class="form-label">Postal address</label>
    <input class="form-control" name="postal_address" value="<?= e($row['postal_address']) ?>"
           placeholder="P.O. Box 123 – 00100, Nairobi"></div>
</div>

<h6 class="text-muted text-uppercase small mt-4 mb-2">GPS location</h6>
<div class="row g-2 mb-2">
  <div class="col-md-3"><input class="form-control" id="lat" name="lat" placeholder="Latitude"  value="<?= e($row['lat']) ?>"></div>
  <div class="col-md-3"><input class="form-control" id="lng" name="lng" placeholder="Longitude" value="<?= e($row['lng']) ?>"></div>
  <div class="col-md-3"><button type="button" class="btn btn-outline-primary w-100" onclick="useMyLocation()">
      <i class="bi bi-geo-alt"></i> Use my location</button></div>
  <div class="col-12 small text-muted">Click anywhere on the map to drop a pin, or drag the existing one.</div>
</div>
<div id="map" class="mb-3"></div>

<h6 class="text-muted text-uppercase small mt-2 mb-2">Directors / Partners / Owners</h6>
<div class="row g-3">
  <div class="col-12"><label class="form-label">List the directors / partners / individuals (one name per line)</label>
    <textarea class="form-control" name="directors" rows="3" placeholder="Jane Doe&#10;John Smith"><?= e($row['directors']) ?></textarea></div>
</div>

<h6 class="text-muted text-uppercase small mt-4 mb-2">Accountant</h6>
<div class="row g-3">
  <div class="col-md-6"><label class="form-label">Accountant's name</label>
    <input class="form-control" name="accountant_name" value="<?= e($row['accountant_name']) ?>"></div>
  <div class="col-md-6"><label class="form-label">Accountant's phone</label>
    <input class="form-control" name="accountant_phone" value="<?= e($row['accountant_phone']) ?>"></div>
</div>

<h6 class="text-muted text-uppercase small mt-4 mb-2">Banking &amp; credit</h6>
<div class="row g-3">
  <div class="col-md-4"><label class="form-label">Bank name</label>
    <input class="form-control" name="bank_name" value="<?= e($row['bank_name']) ?>"></div>
  <div class="col-md-4"><label class="form-label">Bank branch</label>
    <input class="form-control" name="bank_branch" value="<?= e($row['bank_branch']) ?>"></div>
  <div class="col-md-4"><label class="form-label">Credit limit requested (<?= e(APP_CURRENCY) ?>)</label>
    <input class="form-control" type="number" step="0.01" min="0" name="credit_limit" value="<?= e($row['credit_limit']) ?>"></div>

  <div class="col-md-6"><label class="form-label">Payment terms</label>
    <input class="form-control" name="payment_terms" value="<?= e($row['payment_terms']) ?>"
           placeholder="e.g. 30 days from invoice"></div>
  <div class="col-md-3"><label class="form-label">Credit period (days)</label>
    <input class="form-control" type="number" min="0" name="credit_period_days" value="<?= e($row['credit_period_days']) ?>"></div>
</div>

<h6 class="text-muted text-uppercase small mt-4 mb-2">Trade references</h6>
<div class="row g-3">
  <div class="col-12"><label class="form-label">Reference #1</label>
    <textarea class="form-control" name="trade_ref_1" rows="2" placeholder="Name, contact, phone"><?= e($row['trade_ref_1']) ?></textarea></div>
  <div class="col-12"><label class="form-label">Reference #2</label>
    <textarea class="form-control" name="trade_ref_2" rows="2"><?= e($row['trade_ref_2']) ?></textarea></div>
  <div class="col-12"><label class="form-label">Reference #3</label>
    <textarea class="form-control" name="trade_ref_3" rows="2"><?= e($row['trade_ref_3']) ?></textarea></div>
</div>

<h6 class="text-muted text-uppercase small mt-4 mb-2">Signed by</h6>
<div class="row g-3">
  <div class="col-md-5"><label class="form-label">Name of signatory</label>
    <input class="form-control" name="signed_name" value="<?= e($row['signed_name']) ?>"></div>
  <div class="col-md-4"><label class="form-label">Position</label>
    <input class="form-control" name="signed_position" value="<?= e($row['signed_position']) ?>"></div>
  <div class="col-md-3"><label class="form-label">Date signed</label>
    <input class="form-control" type="date" name="signed_at" value="<?= e($row['signed_at']) ?>"></div>
</div>

<?php if ($u['role'] === 'admin'): ?>
  <h6 class="text-muted text-uppercase small mt-4 mb-2">Office use (admin only)</h6>
  <div class="row g-3">
    <div class="col-md-3"><label class="form-label">Assigned rep</label>
      <select class="form-select" name="rep_id">
        <option value="">— None —</option>
        <?php foreach ($reps as $r): ?>
          <option value="<?= (int)$r['id'] ?>" <?= ((int)$row['rep_id'] === (int)$r['id'])?'selected':'' ?>><?= e($r['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label">KYC status</label>
      <select class="form-select" name="kyc_status">
        <?php foreach (['pending','verified','rejected'] as $k): ?>
          <option value="<?= $k ?>" <?= $row['kyc_status']===$k?'selected':'' ?>><?= ucfirst($k) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label">KYC reviewer notes</label>
      <input class="form-control" name="kyc_notes" value="<?= e($row['kyc_notes']) ?>"></div>
  </div>
<?php else: ?>
  <input type="hidden" name="kyc_notes" value="<?= e($row['kyc_notes']) ?>">
<?php endif; ?>

<h6 class="text-muted text-uppercase small mt-4 mb-2">Internal notes</h6>
<textarea class="form-control" name="notes" rows="2"><?= e($row['notes']) ?></textarea>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
  var startLat = parseFloat(document.getElementById('lat').value) || -1.286389;
  var startLng = parseFloat(document.getElementById('lng').value) || 36.817223;
  var map = L.map('map').setView([startLat, startLng], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '(c) OpenStreetMap', maxZoom: 19
  }).addTo(map);
  var marker = null;
  function place(lat,lng){
    if (marker) marker.setLatLng([lat,lng]);
    else        marker = L.marker([lat,lng], {draggable:true}).addTo(map);
    marker.on('dragend', function(){
      var p = marker.getLatLng();
      document.getElementById('lat').value = p.lat.toFixed(7);
      document.getElementById('lng').value = p.lng.toFixed(7);
    });
    document.getElementById('lat').value = lat.toFixed(7);
    document.getElementById('lng').value = lng.toFixed(7);
  }
  if (parseFloat(document.getElementById('lat').value)) {
    place(parseFloat(document.getElementById('lat').value), parseFloat(document.getElementById('lng').value));
  }
  map.on('click', function(e){ place(e.latlng.lat, e.latlng.lng); });
  window.useMyLocation = function(){
    if (!navigator.geolocation) return alert('Geolocation not supported.');
    navigator.geolocation.getCurrentPosition(function(p){
      map.setView([p.coords.latitude, p.coords.longitude], 16);
      place(p.coords.latitude, p.coords.longitude);
    }, function(){ alert('Could not get your location.'); });
  };
})();
</script>
