<?php
/**
 * Floating help button + guided tutorial (admin only).
 *
 * Included from the footer on every page. Renders a small round button in the
 * bottom-right corner; clicking it opens a step-by-step guide that walks an
 * admin through setting the school up in the right order.
 */

$helpUser = function_exists('currentUser') ? currentUser() : null;
if (!$helpUser || ($helpUser['role'] ?? '') !== 'admin') {
    return;
}

$helpPrefix = $assetPrefix ?? '';

$helpSteps = [
    [
        'icon'   => 'graduation-cap',
        'title'  => 'Welcome — start here',
        'lead'   => 'This is the admin side of Academic Excellence. You set up the school structure first, then the classes, then the people. The steps in this guide follow that order.',
        'bullets' => [
            'Every step points at a menu item on the left sidebar.',
            'You can keep this guide open while you work.',
            'Use the dots, Back and Next to move around.',
        ],
        'tip'    => 'First time here? Follow the steps in order, one at a time.',
    ],
    [
        'icon'   => 'landmark',
        'title'  => 'Departments',
        'lead'   => 'Departments group your courses, blocks and subjects.',
        'bullets' => [
            'Open <strong>Departments</strong> from the sidebar.',
            'Add each department with its short code (for example BSIT) and full name.',
            'Everything else in the system hangs off a department.',
        ],
        'link'   => 'pages/departments.php',
        'label'  => 'Go to Departments',
        'tip'    => 'Do this first — courses, subjects and blocks all need a department.',
    ],
    [
        'icon'   => 'book-open',
        'title'  => 'Courses / Subjects',
        'lead'   => 'Add every subject that will be taught, then assign its department.',
        'bullets' => [
            'Open <strong>Courses / Subjects</strong>.',
            'Fill in the subject code and name, choose the department, then save.',
            'Subjects show up on the students&rsquo; My Subjects cards.',
        ],
        'link'   => 'pages/manage-subjects.php',
        'label'  => 'Go to Courses / Subjects',
        'tip'    => 'Only active subjects appear when you build a schedule.',
    ],
    [
        'icon'   => 'grid',
        'title'  => 'Blocks',
        'lead'   => 'A block is a group of students in the same department and year level — for example BSIT 1A.',
        'bullets' => [
            'Open <strong>Blocks</strong>.',
            'Choose the department, the year level and a block code, then save.',
            'Students belong to a block, and so do schedules.',
        ],
        'link'   => 'pages/blocks.php',
        'label'  => 'Go to Blocks',
        'tip'    => 'These blocks become the &ldquo;Class Block&rdquo; choices when you add a schedule.',
    ],
    [
        'icon'   => 'door',
        'title'  => 'Rooms',
        'lead'   => 'Rooms are the physical rooms your classes are held in.',
        'bullets' => [
            'Open <strong>Rooms</strong>.',
            'Add the room name, its floor and (if you like) how many students it seats.',
            'In the schedule form, rooms are grouped by floor and show their seat count.',
        ],
        'link'   => 'pages/rooms.php',
        'label'  => 'Go to Rooms',
        'tip'    => 'Renaming a room updates the schedules that already use it. To retire a room, switch it to inactive instead of deleting it.',
    ],
    [
        'icon'   => 'users',
        'title'  => 'Users (instructors & students)',
        'lead'   => 'Create the accounts that sign in to the app.',
        'bullets' => [
            'Open <strong>Users</strong> and add an instructor or a student.',
            'Login IDs are generated for you, like EMP-2026-0001 and STU-2026-0001.',
            'New accounts start with the default password — users can change it themselves from the menu at the top-right.',
        ],
        'link'   => 'pages/users.php',
        'label'  => 'Go to Users',
        'tip'    => 'Instructors and students only ever see their own sections and records.',
    ],
    [
        'icon'   => 'calendar',
        'title'  => 'Schedules — build the class blocks',
        'lead'   => 'This is the heart of the setup: here you turn a subject into an actual class with a teacher, a room and a time.',
        'bullets' => [
            'Click <strong>Add Block</strong> on the Schedules page.',
            'Choose the Subject, Instructor, Term and Class Block.',
            'Choose the <strong>Room</strong> and Capacity next.',
            'Finally pick the days and times. Times already booked for that room or instructor are greyed out and marked &ldquo;booked&rdquo;.',
            'Click Save Schedule. The block code (B1, B2&hellip;) is generated from the class block you picked.',
        ],
        'link'   => 'pages/schedules.php',
        'label'  => 'Go to Schedules',
        'tip'    => 'Pick the room before the time: the time list updates to show what is already taken for that room.',
    ],
    [
        'icon'   => 'clipboard-list',
        'title'  => 'Enrollments',
        'lead'   => 'Put students into the schedules you just created.',
        'bullets' => [
            'Open <strong>Enrollments</strong>.',
            'Choose the schedule, then add the students who belong to it.',
            'Capacity badges show how full each class is (Full / Almost Full / slots left).',
        ],
        'link'   => 'pages/enrollments.php',
        'label'  => 'Go to Enrollments',
        'tip'    => 'Enroll a whole block at once for regular students, or pick subjects one by one for irregular students.',
    ],
    [
        'icon'   => 'clipboard-check',
        'title'  => 'Attendance & Grades',
        'lead'   => 'These are the day-to-day teaching records.',
        'bullets' => [
            'Instructors record attendance and grades for their own sections.',
            'As admin you can review attendance and grades here — read-only.',
            'Students see their own records in My Grades and My Attendance.',
        ],
        'link'   => 'pages/attendance.php',
        'label'  => 'Go to Attendance',
        'tip'    => 'Grades feed the Reports page and the numbers on each student&rsquo;s dashboard.',
    ],
    [
        'icon'   => 'bar-chart',
        'title'  => 'Reports — and you are all set',
        'lead'   => 'Check how everything is going, then keep the data fresh each term.',
        'bullets' => [
            'Open <strong>Reports</strong> for performance summaries.',
            'Add the new term before you schedule the next semester.',
            'Change your own password from the menu at the top-right.',
        ],
        'link'   => 'pages/reports.php',
        'label'  => 'Go to Reports',
        'tip'    => 'That is the whole tour. Reopen this guide any time with the help button at the bottom-right.',
    ],
];

$helpTotal = count($helpSteps);
?>

<div class="help-widget" id="helpWidget">
    <div class="help-panel" id="helpPanel" role="dialog" aria-modal="false" aria-labelledby="helpPanelTitle" hidden>
        <div class="help-panel-head">
            <div class="help-panel-title">
                <?php echo icon('help', 18); ?>
                <span id="helpPanelTitle">How to use this app</span>
            </div>
            <button type="button" class="help-panel-close" id="helpClose" aria-label="Close the guide"><?php echo icon('x', 16); ?></button>
        </div>

        <div class="help-progress">
            <span class="help-progress-text" id="helpProgressText">Step 1 of <?php echo $helpTotal; ?></span>
            <div class="help-dots" id="helpDots">
                <?php for ($i = 0; $i < $helpTotal; $i++): ?>
                <button type="button" class="help-dot<?php echo $i === 0 ? ' active' : ''; ?>" data-step="<?php echo $i; ?>" aria-label="Step <?php echo $i + 1; ?>"></button>
                <?php endfor; ?>
            </div>
        </div>

        <div class="help-body" id="helpBody">
            <?php foreach ($helpSteps as $index => $step): ?>
            <section class="help-step<?php echo $index === 0 ? ' active' : ''; ?>" data-step="<?php echo $index; ?>">
                <div class="help-step-icon"><?php echo icon($step['icon'], 22); ?></div>
                <h3 class="help-step-title"><?php echo $step['title']; ?></h3>
                <p class="help-step-lead"><?php echo $step['lead']; ?></p>

                <ul class="help-step-list">
                    <?php foreach ($step['bullets'] as $bullet): ?>
                    <li><?php echo $bullet; ?></li>
                    <?php endforeach; ?>
                </ul>

                <?php if (!empty($step['tip'])): ?>
                <p class="help-step-tip"><?php echo icon('check-circle', 14); ?> <span><?php echo $step['tip']; ?></span></p>
                <?php endif; ?>

                <?php if (!empty($step['link'])): ?>
                <a class="btn btn-secondary btn-sm help-step-link" href="<?php echo htmlspecialchars($helpPrefix . $step['link']); ?>">
                    <?php echo htmlspecialchars($step['label']); ?>
                </a>
                <?php endif; ?>
            </section>
            <?php endforeach; ?>
        </div>

        <div class="help-panel-foot">
            <button type="button" class="btn btn-secondary btn-sm" id="helpPrev">Back</button>
            <span class="help-foot-spacer"></span>
            <button type="button" class="btn btn-primary btn-sm" id="helpNext">Next</button>
        </div>
    </div>

    <button type="button" class="help-fab" id="helpFab" aria-controls="helpPanel" aria-expanded="false" title="How to use this app">
        <?php echo icon('help', 22); ?>
        <span class="help-fab-label">Help</span>
    </button>
</div>

<script>
(function() {
    var widget  = document.getElementById('helpWidget');
    var panel   = document.getElementById('helpPanel');
    var fab     = document.getElementById('helpFab');
    var closeBtn = document.getElementById('helpClose');
    var prevBtn = document.getElementById('helpPrev');
    var nextBtn = document.getElementById('helpNext');
    var body    = document.getElementById('helpBody');
    var dots    = document.getElementById('helpDots');
    var progressText = document.getElementById('helpProgressText');
    var steps   = body.querySelectorAll('.help-step');
    var dotEls  = dots.querySelectorAll('.help-dot');
    var current = 0;
    var total   = steps.length;

    // Remember where the admin stopped so the guide resumes there.
    var storageKey = 'helpTutorialStep';
    var saved = parseInt(window.localStorage.getItem(storageKey), 10);
    if (!isNaN(saved) && saved >= 0 && saved < total) {
        current = saved;
    }

    function show(index) {
        current = Math.max(0, Math.min(index, total - 1));
        steps.forEach(function(step, i) {
            step.classList.toggle('active', i === current);
        });
        dotEls.forEach(function(dot, i) {
            dot.classList.toggle('active', i === current);
        });
        progressText.textContent = 'Step ' + (current + 1) + ' of ' + total;
        prevBtn.disabled = current === 0;
        nextBtn.textContent = current === total - 1 ? 'Done' : 'Next';
        body.scrollTop = 0;
        try { window.localStorage.setItem(storageKey, String(current)); } catch (e) {}
    }

    function open() {
        panel.hidden = false;
        fab.setAttribute('aria-expanded', 'true');
        widget.classList.add('open');
        show(current);
    }

    function close() {
        panel.hidden = true;
        fab.setAttribute('aria-expanded', 'false');
        widget.classList.remove('open');
        fab.focus();
    }

    function toggle() {
        if (panel.hidden) { open(); } else { close(); }
    }

    fab.addEventListener('click', toggle);
    closeBtn.addEventListener('click', close);
    prevBtn.addEventListener('click', function() { show(current - 1); });
    nextBtn.addEventListener('click', function() {
        if (current === total - 1) { close(); } else { show(current + 1); }
    });
    dotEls.forEach(function(dot) {
        dot.addEventListener('click', function() {
            show(parseInt(dot.dataset.step, 10) || 0);
        });
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !panel.hidden) { close(); }
    });

    show(current);
})();
</script>
