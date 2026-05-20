<?php
// =====================================================================
// specialty-mocks.php — phone-shaped specialty mock UIs.
// Ported from assets/js/specialty-mocks.jsx.
//
// Each renderer accepts no arguments. Call render_spec_mock('gp')
// (or 'homeo', 'dental', 'derma', 'peds', 'physio') to echo the HTML.
// =====================================================================

require_once __DIR__ . '/helpers.php';

if (!function_exists('render_spec_mock')) {

function _spec_mock_phone_shell_open(string $tint = 'var(--bg-2)'): string
{
    return '<div class="device-frame" style="width: 280px; margin: 0 auto; background: #0A0A0A; border-radius: 36px; padding: 6px; box-shadow: 0 20px 60px rgba(0,0,0,0.18);">'
        . '<div class="device-screen" style="background: ' . e($tint) . '; border-radius: 30px; overflow: hidden; min-height: 540px; color: var(--ink);">'
        . '<div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 20px 6px; font-size: 11px; font-weight: 600;">'
        . '<span>9:41</span>'
        . '<span style="display: inline-flex; gap: 4px; align-items: center;">'
        . '<span style="width: 14px; height: 8px; border: 0.8px solid currentColor; border-radius: 2px; position: relative; display: inline-block;">'
        . '<span style="position: absolute; inset: 1px; background: currentColor; border-radius: 1px;"></span></span></span></div>';
}
function _spec_mock_phone_shell_close(): string { return '</div></div>'; }

function render_spec_mock(string $key): void
{
    switch ($key) {
        case 'gp':
            echo _spec_mock_phone_shell_open();
            ?>
            <div style="padding: 8px 16px 14px;">
                <div style="font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 500;">Now in room</div>
                <div style="font-size: 19px; font-weight: 500; letter-spacing: -0.3px; margin-top: 2px;">Riya Mehta, 38</div>
                <div style="display: flex; gap: 6px; margin-top: 6px; flex-wrap: wrap;">
                    <span style="font-size: 10px; padding: 2px 7px; background: var(--teal-50); color: var(--teal-800); border-radius: 8px;">HTN</span>
                    <span style="font-size: 10px; padding: 2px 7px; background: #FFF3E0; color: #8B5500; border-radius: 8px;">F/U</span>
                </div>
            </div>
            <div style="padding: 0 12px; display: flex; flex-direction: column; gap: 8px;">
                <div style="background: #fff; border-radius: 12px; padding: 12px 14px; border: 0.5px solid var(--line);">
                    <div style="font-size: 10px; color: var(--mute); font-weight: 500; text-transform: uppercase; letter-spacing: 0.06em;">Vitals · captured 9:28</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-top: 8px;">
                        <?php foreach ([['BP','138/86','mmHg','var(--amber, #d97706)'],['HR','78','bpm','var(--ink)'],['SpO₂','98','%','#1B8B3D']] as [$lbl,$v,$u,$col]): ?>
                        <div>
                            <div style="font-size: 9px; color: var(--mute);"><?= e($lbl) ?></div>
                            <div style="font-size: 16px; font-weight: 500; color: <?= $col ?>; letter-spacing: -0.3px;"><?= e($v) ?></div>
                            <div style="font-size: 9px; color: var(--mute);"><?= e($u) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div style="background: #fff; border-radius: 12px; padding: 12px 14px; border: 0.5px solid var(--line);">
                    <div style="font-size: 10px; color: var(--mute); font-weight: 500; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 6px;">Common Rx · suggested</div>
                    <div style="font-size: 12px; font-weight: 500;">Amlodipine 5mg</div>
                    <div style="font-size: 10.5px; color: var(--mute); margin-top: 2px;">1 tab OD · 30 days</div>
                </div>
                <button type="button" style="background: var(--ink); color: #fff; border: 0; border-radius: 10px; padding: 12px; font-size: 13px; font-weight: 500; cursor: pointer;">Start visit notes</button>
            </div>
            <?php
            echo _spec_mock_phone_shell_close();
            return;

        case 'homeo':
            echo _spec_mock_phone_shell_open();
            ?>
            <div style="padding: 8px 16px 14px;">
                <div style="font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 500;">Case taking</div>
                <div style="font-size: 18px; font-weight: 500; letter-spacing: -0.3px; margin-top: 2px;">Suresh Kumar, 52</div>
            </div>
            <div style="padding: 0 12px; display: flex; flex-direction: column; gap: 8px;">
                <div style="background: #fff; border-radius: 12px; padding: 12px 14px; border: 0.5px solid var(--line);">
                    <div style="font-size: 10px; color: var(--mute); font-weight: 500; text-transform: uppercase;">Mental generals</div>
                    <div style="font-size: 12px; color: var(--ink-2); line-height: 1.55; margin-top: 4px;">Anxiety, anticipation. Better lying on right side. Worse 4–8pm.</div>
                </div>
                <div style="background: #fff; border-radius: 12px; padding: 12px 14px; border: 0.5px solid var(--line);">
                    <div style="font-size: 10px; color: var(--mute); font-weight: 500; text-transform: uppercase; margin-bottom: 6px;">Selected remedy</div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-size: 14px; font-weight: 500;">Lycopodium</div>
                            <div style="font-size: 10.5px; color: var(--mute);">Miasm: Sycotic</div>
                        </div>
                        <div style="background: var(--teal-50); color: var(--teal-800); font-size: 11px; font-weight: 600; padding: 4px 9px; border-radius: 8px;">200C</div>
                    </div>
                </div>
                <div style="background: var(--bg-2); border-radius: 10px; padding: 8px 12px; font-size: 10.5px; color: var(--mute);">
                    ⚠ Antidote: coffee, camphor. Wait 4 weeks for assessment.
                </div>
            </div>
            <?php
            echo _spec_mock_phone_shell_close();
            return;

        case 'dental':
            echo _spec_mock_phone_shell_open();
            ?>
            <div style="padding: 8px 16px 12px;">
                <div style="font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 500;">Dental chart · FDI</div>
                <div style="font-size: 17px; font-weight: 500; letter-spacing: -0.3px; margin-top: 2px;">Emma Whitfield, 32</div>
            </div>
            <div style="padding: 0 12px;">
                <div style="background: #fff; border-radius: 12px; padding: 12px; border: 0.5px solid var(--line);">
                    <div style="display: grid; grid-template-columns: repeat(8, 1fr); gap: 3px;">
                        <?php for ($i = 0; $i < 16; $i++):
                            $c = $i === 2 ? '#FF453A' : ($i === 5 ? '#FF9F0A' : ($i === 11 ? 'var(--teal-600)' : '#E5E5EA'));
                            $op = ($c === '#E5E5EA') ? 0.6 : 1;
                        ?>
                        <div style="aspect-ratio: 1; border-radius: 4px; background: <?= $c ?>; opacity: <?= $op ?>;"></div>
                        <?php endfor; ?>
                    </div>
                    <div style="height: 8px;"></div>
                    <div style="display: grid; grid-template-columns: repeat(8, 1fr); gap: 3px;">
                        <?php for ($i = 0; $i < 16; $i++):
                            $c = $i === 8 ? 'var(--teal-600)' : ($i === 13 ? '#FF453A' : '#E5E5EA');
                            $op = ($c === '#E5E5EA') ? 0.6 : 1;
                        ?>
                        <div style="aspect-ratio: 1; border-radius: 4px; background: <?= $c ?>; opacity: <?= $op ?>;"></div>
                        <?php endfor; ?>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 12px; font-size: 9.5px; color: var(--mute);">
                        <span style="display: inline-flex; align-items: center; gap: 4px;"><span style="width: 7px; height: 7px; background: #FF453A; border-radius: 2px;"></span>Caries</span>
                        <span style="display: inline-flex; align-items: center; gap: 4px;"><span style="width: 7px; height: 7px; background: #FF9F0A; border-radius: 2px;"></span>Watch</span>
                        <span style="display: inline-flex; align-items: center; gap: 4px;"><span style="width: 7px; height: 7px; background: var(--teal-600); border-radius: 2px;"></span>Filled</span>
                    </div>
                </div>
                <div style="margin-top: 10px; background: #fff; border-radius: 12px; padding: 10px 12px; border: 0.5px solid var(--line);">
                    <div style="display: flex; justify-content: space-between; font-size: 12px;">
                        <span style="font-weight: 500;">Treatment plan</span>
                        <span style="color: var(--teal-700); font-weight: 500;">$1,240</span>
                    </div>
                    <div style="font-size: 10.5px; color: var(--mute); margin-top: 2px;">3 visits · composite #16, RCT #26, scaling</div>
                </div>
            </div>
            <?php
            echo _spec_mock_phone_shell_close();
            return;

        case 'derma':
            echo _spec_mock_phone_shell_open();
            ?>
            <div style="padding: 8px 16px 12px;">
                <div style="font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 500;">Lesion timeline · L. forearm</div>
                <div style="font-size: 17px; font-weight: 500; letter-spacing: -0.3px; margin-top: 2px;">Marta Lopez, 29</div>
            </div>
            <div style="padding: 0 12px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <?php foreach ([['Before', '#E8C9B6', 'Mar 4', '#A86040'], ['Week 6', '#F3D9C6', 'Apr 15', '#C88E70']] as $p): list($lbl, $skin, $when, $dot) = $p; ?>
                    <div style="background: #fff; border-radius: 12px; overflow: hidden; border: 0.5px solid var(--line);">
                        <div style="aspect-ratio: 1; background: linear-gradient(135deg, <?= $skin ?>, #D8B69A); position: relative;">
                            <div style="position: absolute; top: 38%; left: 40%; width: 18px; height: 18px; border-radius: 50%; background: <?= $dot ?>; box-shadow: 0 0 0 1px rgba(255,255,255,0.6);"></div>
                        </div>
                        <div style="padding: 6px 8px;">
                            <div style="font-size: 11px; font-weight: 500;"><?= e($lbl) ?></div>
                            <div style="font-size: 9.5px; color: var(--mute);"><?= e($when) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 10px; background: #fff; border-radius: 12px; padding: 10px 12px; border: 0.5px solid var(--line);">
                    <div style="display: flex; justify-content: space-between; font-size: 12px;">
                        <span style="font-weight: 500;">Measurement</span>
                        <span style="color: #1B8B3D; font-weight: 500;">−38%</span>
                    </div>
                    <div style="font-size: 10.5px; color: var(--mute); margin-top: 2px;">9.2mm → 5.7mm · responding well</div>
                </div>
            </div>
            <?php
            echo _spec_mock_phone_shell_close();
            return;

        case 'peds':
            echo _spec_mock_phone_shell_open();
            ?>
            <div style="padding: 8px 16px 12px;">
                <div style="font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 500;">Growth · 14 mo</div>
                <div style="font-size: 17px; font-weight: 500; letter-spacing: -0.3px; margin-top: 2px;">Sneha Iyer · F</div>
            </div>
            <div style="padding: 0 12px;">
                <div style="background: #fff; border-radius: 12px; padding: 14px; border: 0.5px solid var(--line);">
                    <div style="font-size: 10px; color: var(--mute); font-weight: 500; text-transform: uppercase;">Weight-for-age · WHO</div>
                    <svg viewBox="0 0 200 100" style="width: 100%; height: 100px; margin-top: 6px;">
                        <path d="M0 80 Q40 60 80 50 T160 35 L200 30" stroke="rgba(0,0,0,0.1)" fill="none" stroke-width="1" stroke-dasharray="3 3"/>
                        <path d="M0 90 Q40 78 80 70 T160 58 L200 55" stroke="rgba(0,0,0,0.1)" fill="none" stroke-width="1" stroke-dasharray="3 3"/>
                        <path d="M0 95 Q40 88 80 82 T160 75 L200 73" stroke="rgba(0,0,0,0.1)" fill="none" stroke-width="1" stroke-dasharray="3 3"/>
                        <path d="M0 88 Q40 76 80 65 L120 55 L160 48" stroke="var(--teal-600)" fill="none" stroke-width="2"/>
                        <?php foreach ([[0,88],[40,76],[80,65],[120,55],[160,48]] as [$x, $y]): ?>
                        <circle cx="<?= $x ?>" cy="<?= $y ?>" r="3" fill="var(--teal-600)"/>
                        <?php endforeach; ?>
                    </svg>
                    <div style="display: flex; justify-content: space-between; font-size: 10px; color: var(--mute); margin-top: 6px;">
                        <span>9.4 kg · 62nd %ile</span>
                        <span style="color: #1B8B3D; font-weight: 500;">On track</span>
                    </div>
                </div>
                <div style="margin-top: 10px; background: var(--teal-50); border-radius: 10px; padding: 10px 12px; font-size: 11.5px; color: var(--teal-800);">
                    <strong style="font-weight: 600;">Vaccine due:</strong> MMR booster · book within 30 days
                </div>
            </div>
            <?php
            echo _spec_mock_phone_shell_close();
            return;

        case 'physio':
            echo _spec_mock_phone_shell_open();
            ?>
            <div style="padding: 8px 16px 12px;">
                <div style="font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 500;">Week 3 program</div>
                <div style="font-size: 17px; font-weight: 500; letter-spacing: -0.3px; margin-top: 2px;">Lower back · L4-L5</div>
            </div>
            <div style="padding: 0 12px; display: flex; flex-direction: column; gap: 8px;">
                <?php
                $exercises = [
                    ['Cat-cow stretch', '3×10', true, false],
                    ['Bird dog', '3×8 each side', true, false],
                    ['Glute bridge', '3×12', false, true],
                    ['Dead bug', '3×10', false, false],
                ];
                foreach ($exercises as [$name, $reps, $done, $today]):
                    $iconBg = $today ? 'var(--teal-50)' : 'var(--bg-2)';
                    $iconCol = $today ? 'var(--teal-700)' : 'var(--mute)';
                ?>
                <div style="background: #fff; border-radius: 12px; padding: 10px 12px; border: 0.5px solid var(--line); display: flex; align-items: center; gap: 10px;">
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: <?= $iconBg ?>; display: grid; place-items: center; color: <?= $iconCol ?>; font-size: 14px;">▶</div>
                    <div style="flex: 1;">
                        <div style="font-size: 12.5px; font-weight: 500;"><?= e($name) ?></div>
                        <div style="font-size: 10.5px; color: var(--mute);"><?= e($reps) ?></div>
                    </div>
                    <?php if ($done): ?><span style="color: var(--teal-600); font-weight: 600;">✓</span><?php endif; ?>
                    <?php if ($today): ?><span style="font-size: 10px; font-weight: 500; color: var(--teal-700);">Today</span><?php endif; ?>
                </div>
                <?php endforeach; ?>
                <div style="font-size: 10.5px; color: var(--mute); text-align: center; margin-top: 4px;">3 of 4 done · keep going 💪</div>
            </div>
            <?php
            echo _spec_mock_phone_shell_close();
            return;
    }

    // Unknown key: render empty placeholder.
    echo '<div style="min-height: 540px; background: var(--bg-2); border-radius: 14px; display: grid; place-items: center; color: var(--mute);">Mock not found: ' . e($key) . '</div>';
}

} // function_exists guard
