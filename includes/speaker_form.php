<?php
// Template partiel pour la configuration d'un intervenant (Régie Protec Live)
$num = isset($speakerNum) ? (int)$speakerNum : 1;
$sideDefault = ($num % 2 === 1) ? 'left' : 'right';
$sideLabel = ($num === 1) ? 'Gauche' : (($num === 2) ? 'Droite' : (($num === 3) ? 'Gauche' : 'Droite'));
?>
<div class="section-title"><i class="fa-solid fa-user"></i> HUD Intervenant <?= $num ?> (<?= $sideLabel ?>)</div>
<div class="form-grid">
    <div class="form-group">
        <label for="speaker<?= $num ?>Name">Nom de l'intervenant <?= $num ?></label>
        <div class="input-wrapper">
            <i class="fa-solid fa-user"></i>
            <input type="text" id="speaker<?= $num ?>Name" placeholder="Ex: <?= ($num === 1) ? 'CAPITAINE JEAN DUPONT' : (($num === 2) ? 'DOCTEUR ANNE MARTIN' : (($num === 3) ? 'LIEUTENANT MARC BOULARD' : 'SOPHIE DUBOIS')) ?>">
        </div>
    </div>
    <div class="form-group">
        <label for="speaker<?= $num ?>Role">Fonction / Rôle / Métier</label>
        <div class="input-wrapper">
            <i class="fa-solid fa-briefcase"></i>
            <input type="text" id="speaker<?= $num ?>Role" placeholder="Ex: Secouriste - Protection Civile">
        </div>
    </div>
    <div class="form-group">
        <label for="speaker<?= $num ?>Apc">APC (Département) - Laisser vide pour cacher</label>
        <div class="input-wrapper">
            <i class="fa-solid fa-map-marker-alt"></i>
            <input type="text" id="speaker<?= $num ?>Apc" placeholder="Ex: Paris">
        </div>
    </div>
    <div class="form-group">
        <label for="speaker<?= $num ?>Icon">Icône de l'intervenant <?= $num ?></label>
        <div class="input-wrapper">
            <i class="fa-solid fa-icons"></i>
            <select id="speaker<?= $num ?>Icon" class="icon-select">
                <option value="fa-user">&#xf007;</option>
                <option value="fa-user-tie">&#xf508;</option>
                <option value="fa-user-doctor">&#xf0f0;</option>
                <option value="fa-user-nurse">&#xf482;</option>
                <option value="fa-user-shield">&#xf47f;</option>
                <option value="fa-user-astronaut">&#xf554;</option>
                <option value="fa-user-ninja">&#xf481;</option>
                <option value="fa-shield-halved">&#xf3ed;</option>
                <option value="fa-microphone">&#xf130;</option>
                <option value="fa-video">&#xf03d;</option>
                <option value="fa-bullhorn">&#xf0a1;</option>
                <option value="fa-bolt">&#xf0e7;</option>
                <option value="fa-stethoscope">&#xf0fa;</option>
                <option value="fa-briefcase-medical">&#xf54c;</option>
                <option value="fa-ambulance">&#xf0f9;</option>
                <option value="fa-fire-extinguisher">&#xf4d7;</option>
                <option value="fa-fire">&#xf06d;</option>
                <option value="fa-star">&#xf005;</option>
                <option value="fa-crown">&#xf594;</option>
                <option value="fa-info">&#xf129;</option>
                <option value="fa-question">&#xf128;</option>
                <option value="fa-users">&#xf0c0;</option>
                <option value="fa-phone">&#xf095;</option>
                <option value="fa-wifi">&#xf1eb;</option>
                <option value="fa-clock">&#xf017;</option>
                <option value="fa-gavel">&#xf0e3;</option>
                <option value="fa-house">&#xf015;</option>
                <option value="fa-book">&#xf02d;</option>
                <option value="fa-circle-exclamation">&#xf06a;</option>
                <option value="fa-crosshairs">&#xf05b;</option>
            </select>
        </div>
    </div>
    <div class="form-group">
        <label for="speaker<?= $num ?>Side">Côté d'affichage</label>
        <div class="input-wrapper">
            <i class="fa-solid fa-align-left"></i>
            <select id="speaker<?= $num ?>Side">
                <option value="left" <?= ($sideDefault === 'left') ? 'selected' : '' ?>>Gauche (Slide gauche, aligné à gauche)</option>
                <option value="right" <?= ($sideDefault === 'right') ? 'selected' : '' ?>>Droite (Slide droite, aligné à droite)</option>
            </select>
        </div>
    </div>
    <div class="form-group">
        <label for="speaker<?= $num ?>vdoId">ID de Stream VDO.Ninja (Caméra)</label>
        <div class="input-wrapper">
            <i class="fa-solid fa-video"></i>
            <input type="text" id="speaker<?= $num ?>vdoId" placeholder="Ex: proteclive_sp<?= $num ?>">
        </div>
    </div>
    <div class="form-group" style="display: flex; align-items: flex-end;">
        <button type="button" class="btn-submit" onclick="copyVdoLink(<?= $num ?>, 'cam')" style="background-color: rgba(255,255,255,0.08); border: 1px dashed rgba(255,255,255,0.2); color: #fff; width: 100%; height: 50px;">
            <i class="fa-solid fa-copy"></i> Lien Caméra
        </button>
    </div>
    <div class="form-group">
        <label for="speaker<?= $num ?>vdoScreenId">ID de Stream VDO.Ninja (Écran)</label>
        <div class="input-wrapper">
            <i class="fa-solid fa-display"></i>
            <input type="text" id="speaker<?= $num ?>vdoScreenId" placeholder="Ex: proteclive_sp<?= $num ?>_screen">
        </div>
    </div>
    <div class="form-group" style="display: flex; align-items: flex-end;">
        <button type="button" class="btn-submit" onclick="copyVdoLink(<?= $num ?>, 'screen')" style="background-color: rgba(255,255,255,0.08); border: 1px dashed rgba(255,255,255,0.2); color: #fff; width: 100%; height: 50px;">
            <i class="fa-solid fa-copy"></i> Lien Écran
        </button>
    </div>
</div>
