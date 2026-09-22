<?php // Shared ID-maker preview (front/back/corporate). Requires $companyLogo, $companyName set by parent. ?>
<div class="card">
  <h2>Live Preview — Front</h2>
  <div class="dx-front t1" id="dxFront">
    <div class="dx-top" data-el="dTop"></div>
    <div class="dx-head">
      <img id="dLogo" data-el="dLogo" src="<?= htmlspecialchars($companyLogo) ?>" alt="company logo">
      <div class="dx-coname" id="dCoName" data-el="dCoName"><?= htmlspecialchars($companyName) ?></div>
    </div>
    <div class="dx-photo"><img id="dPhoto" data-el="dPhoto" src="https://ui-avatars.com/api/?name=Staff&size=220&background=0e4b8f&color=fff" alt="staff photo"></div>
    <div class="dx-name" id="dName" data-el="dName">EMPLOYEE NAME</div>
    <div class="dx-title" id="dTitle" data-el="dTitle">Job Title</div>
    <div class="dx-bot" data-el="dBot">
      <div class="dx-bar"><svg id="dBarcode" data-el="dBar"></svg><div id="dQrFront" data-el="dQrFront"></div></div>
      <div class="dx-idline" id="dId" data-el="dId">ID EMP-0000</div>
      <span id="dIdFoot" data-el="dIdFoot">ID EMP-0000</span>
    </div>
  </div>
  <div class="corp-card" id="cardCorp" style="display:none">
    <div class="corp-logo-wrap"><img id="cLogo" data-el="cLogo" src="<?= htmlspecialchars($companyLogo) ?>" alt="company logo"></div>
    <div class="corp-photo-wrap"><img class="corp-photo" id="cPhoto" data-el="cPhoto" src="https://ui-avatars.com/api/?name=Staff&size=196&background=0e4b8f&color=fff" alt="staff photo"></div>
    <div class="corp-name" id="cName" data-el="cName">EMPLOYEE NAME</div>
    <div class="corp-role" id="cRole" data-el="cRole">Staff</div>
    <div class="corp-bottom">
      <div class="corp-id" id="cId" data-el="cId">ID: EMP-0000</div>
      <div class="corp-qr" id="qrcodeCorp" data-el="cQr"></div>
    </div>
    <div class="corp-wave">
      <svg viewBox="0 0 340 56" preserveAspectRatio="none"><path d="M0,28 C60,10 120,10 170,24 C220,38 280,34 340,20 L340,56 L0,56 Z" fill="#7cc242"/><path d="M0,38 C70,22 140,22 200,34 C250,43 300,42 340,30 L340,56 L0,56 Z" fill="#1b75bb"/></svg>
    </div>
  </div>
  <div class="btns noprint">
    <button class="ghost" onclick="downloadPNG('dxFront','staff-front.png')">Download front PNG</button>
    <button class="ghost" onclick="downloadPNG('cardCorp','staff-corporate.png')">Download corporate PNG</button>
  </div>
</div>

<div class="card">
  <h2>Live Preview — Back</h2>
  <div class="dx-back t1" id="dxBack">
    <div class="dx-b-top" data-el="dBTop"></div>
    <div class="dx-b-head">
      <img id="dLogoB" data-el="dLogoB" src="<?= htmlspecialchars($companyLogo) ?>" alt="company logo">
      <div class="dx-b-coname" id="dCoNameB" data-el="dCoNameB"><?= htmlspecialchars($companyName) ?></div>
    </div>
    <div class="dx-b-h" data-el="dBHead" id="dBHeadT">TERMS & CONDITIONS</div>
    <div class="dx-b-terms" data-el="dTerms" id="dBTerms">
      <p><b>Identification:</b> Carry this ID card at all times during working hours for identification purposes.</p>
      <p><b>Authorized Use:</b> This card is strictly for official use and must not be shared or used for unauthorized purposes.</p>
    </div>
    <div class="dx-b-contact" data-el="dContact">
      <div class="dx-crow" data-el="dRowP"><span class="ic">☎</span><div><b data-lab="back_phone">Phone</b><span id="dBPhone"><?= htmlspecialchars($companyPhone) ?></span></div></div>
      <div class="dx-crow" data-el="dRowE"><span class="ic">✉</span><div><b data-lab="back_email">Email</b><span id="dBEmail"><?= htmlspecialchars($companyEmail) ?></span></div></div>
      <div class="dx-crow" data-el="dRowA"><span class="ic">📍</span><div><b data-lab="back_address">Address</b><span id="dBAddr"><?= htmlspecialchars($companyAddr) ?></span></div></div>
    </div>
    <div class="dx-b-code">
      <svg id="dBarcodeB" data-el="dBarB"></svg>
      <div class="dx-b-qr" id="dQrB" data-el="dQrB"></div>
    </div>
    <div class="dx-b-idlines" data-el="dBLines" id="dBackFields"><div>ID No : EMP-0000</div></div>
    <div class="dx-b-bot" data-el="dBBot"></div>
  </div>
  <div class="btns noprint">
    <button class="ghost" onclick="downloadPNG('dxBack','staff-back.png')">Download back PNG</button>
    <button class="ghost" onclick="window.print()">Print</button>
  </div>
</div>
