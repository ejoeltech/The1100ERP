<?php // Layout customizer controls (drag + sliders), shared by maker/produce pages. ?>
<h2 style="margin-top:18px">Customize Layout</h2>
<p style="color:var(--mut);font-size:12.5px;margin:0 0 6px">Drag <b>any</b> element directly on the cards, or grab the teal resize handle. Fine-tune with sliders — layout saves with the template.</p>
<label>Element</label>
<select id="elSelect"></select>
<label>Position X — <span id="valX">0</span>px</label>
<input id="rngX" type="range" min="-200" max="200" value="0">
<label>Position Y — <span id="valY">0</span>px</label>
<input id="rngY" type="range" min="-200" max="200" value="0">
<label><span id="sizeLabel">Size</span> — <span id="valSize"></span></label>
<input id="rngSize" type="range" min="10" max="300" value="100">
<div class="row2" style="align-items:end">
  <div><label><input id="elVisible" type="checkbox" checked style="width:auto"> Visible</label></div>
  <div><button class="ghost" onclick="resetLayout()" style="width:100%">Reset layout</button></div>
</div>
