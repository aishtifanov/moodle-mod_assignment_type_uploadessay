<?php // $Id: assignment.class.php,v 1.0 2021/04/12 Shtifanov

/**
 * Extend the base assignment class for assignments where you upload a essay as a single file
 *
 */
class assignment_uploadessay extends assignment_base {


    function print_student_answer($userid, $return=false){
           global $CFG, $USER;

        $filearea = $this->file_area_name($userid);

        $output = '';

        if ($basedir = $this->file_area($userid)) {
            if ($files = get_directory_list($basedir)) {
                require_once($CFG->libdir.'/filelib.php');
                foreach ($files as $key => $file) {

                    $icon = mimeinfo('icon', $file);
                    $ffurl = get_file_url("$filearea/$file");

                    //died right here
                    //require_once($ffurl);
                    $output = '<img src="'.$CFG->pixpath.'/f/'.$icon.'" class="icon" alt="'.$icon.'" />'.
                            '<a href="'.$ffurl.'" >'.$file.'</a><br />';
                }
            }
        }

        $output = '<div class="files">'.$output.'</div>';
        return $output;
    }

    // кон
    function assignment_uploadessay($cmid='staticonly', $assignment=NULL, $cm=NULL, $course=NULL) {
        parent::assignment_base($cmid, $assignment, $cm, $course);
        $this->type = 'uploadessay';
    }

    // переопределенный метод для отображения страниц модуля
    // 1 страница view_intro()
    // 2 страница select_theme_essay()
    // 3 страница finish_page()
    // 4 страница finish_page()
    function view() {

        global $USER, $CFG;

        $context = get_context_instance(CONTEXT_MODULE, $this->cm->id);
        require_capability('mod/assignment:view', $context);

        add_to_log($this->course->id, "assignment", "view", "view.php?id={$this->cm->id}", $this->assignment->id, $this->cm->id);

        // для отобржения дерева тем сочинений подключаем jQuery и свой JS-файл
        require_js($CFG->wwwroot.'/lib/jquery/js/jquery-1.9.1.js');
        require_js($CFG->wwwroot.'/lib/jquery/plugins/chosen/chosen.jquery.js');
        require_js($CFG->wwwroot.'/mod/assignment/type/uploadessay/tree.js');

        $this->view_header();

//        $d = get_records('z_essay_submissions');    print_object($d);
//        $d = get_records('z_essay_themes');        print_object($d);

        // проверяем есть ли запись для данного пользователя в таблице z_essay_submissions
        // если есть, то на первой странице он уже выбрал две галочки и теперь надо проверить выбрал ли он тему
        if ($essay = get_record_select('z_essay_submissions', "userid=$USER->id and assignmentid={$this->assignment->id}")) {
            // print_object($essay);
            // если тема не задана, то проверяем отправку данных
            if ($essay->essaythemeid == 0) {
                if ($frm = data_submitted()) { // сюда мы попадем со страницы выбора темы после нажатия на кнопку " Я подтверждаю выбор темы "
                    // print_object($frm);
                    $aid = $this->assignment->id;
                    set_field_select('z_essay_submissions', 'essaythemeid', $frm->buttonCheck, "userid = $USER->id and assignmentid=$aid");
                    set_field_select('z_essay_submissions', 'timestart', time(), "userid = $USER->id and assignmentid=$aid");
                    $this->finish_page($context);
                } else {  // иначе показываем страницу с выбором темы сочинения
                    $this->select_theme_essay();
                }
            } else {  // если тема задана, то показываем последнюю страницу
                $this->finish_page($context);
            }
        } else {  // если записи в таблице нет, то или обрабатываем нажатие кнопки "Начать испытание"
            if ($frm = data_submitted())    {
                // print_object($frm);
                $rec = new stdClass();
                $rec->assignmentid=$this->assignment->id;
                $rec->userid=$USER->id;
                $rec->isconfirm = $frm->isconfirm;
                $rec->ispromise = $frm->ispromise;
                insert_record('z_essay_submissions', $rec);
                $this->select_theme_essay();
            } else { // или показываем стартовую страницу с инструкцией
                $this->view_intro();
            }
        }

        $this->view_footer();
    }


    function view_upload_form() {
        global $CFG;
        $struploadafile = 'Загрузка документа с отсканированным сочинением в одном из следующих форматов: pdf, jpeg, jpg, png. ';// get_string("uploadafile");

        $maxbytes = $this->assignment->maxbytes == 0 ? $this->course->maxbytes : $this->assignment->maxbytes;
        $strmaxsize = get_string('maxsize', '', display_size($maxbytes));

        echo '<div style="text-align:center">';
        echo '<form enctype="multipart/form-data" method="post" '.
             "action=\"$CFG->wwwroot/mod/assignment/upload.php\">";
        echo '<fieldset class="invisiblefieldset">';
        echo "<p>$struploadafile ($strmaxsize)</p>";
        echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
        // require_once($CFG->libdir.'/uploadlib.php');
        // upload_print_form_fragment(1,array('newfile'),false,null,0,$this->assignment->maxbytes,false);
        echo '<input type="hidden" name="MAX_FILE_SIZE" value="'. $this->assignment->maxbytes .'" />'."\n";
        echo '<input type="file" size="50" name="newfile" alt="newfile" onchange="handleDisable2()"/><br />'."\n";

        echo '<p></p><input type="checkbox" name="ispromise2" value="1" onclick="handleDisable2()"> <em>Я подтверждаю, что потратил 120 минут на написание работы</em></p><br>';

        echo '<p><input type="submit" name="save" value=" Завершить вступительное испытание " disabled /></p>';
        echo '</fieldset>';
        echo '</form>';
        echo '</div>';
    }


    function upload() {

        global $CFG, $USER;

        require_capability('mod/assignment:submit', get_context_instance(CONTEXT_MODULE, $this->cm->id));

        $this->view_header(get_string('upload'));

        $filecount = $this->count_user_files($USER->id);
        $submission = $this->get_submission($USER->id);
        if ($this->isopen() && (!$filecount || $this->assignment->resubmit || !$submission->timemarked)) {
            if ($submission = $this->get_submission($USER->id)) {
                //TODO: change later to ">= 0", to prevent resubmission when graded 0
                if (($submission->grade > 0) and !$this->assignment->resubmit) {
                    notify(get_string('alreadygraded', 'assignment'));
                }
            }

            $dir = $this->file_area_name($USER->id);

            require_once($CFG->dirroot.'/lib/uploadlib.php');
            $um = new upload_manager('newfile',true,false,$this->course,false,$this->assignment->maxbytes);
            if ($um->process_file_uploads($dir)) {
                $newfile_name = $um->get_new_filename();
                if ($submission) {
                    $submission->timemodified = time();
                    $submission->numfiles     = 1;
                    $submission->submissioncomment = addslashes($submission->submissioncomment);
                    unset($submission->data1);  // Don't need to update this.
                    unset($submission->data2);  // Don't need to update this.
                    if (update_record("assignment_submissions", $submission)) {
                        add_to_log($this->course->id, 'assignment', 'upload',
                                'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
                        $submission = $this->get_submission($USER->id);
                        $this->update_grade($submission);
                        $this->email_teachers($submission);
                        print_heading(get_string('uploadedfile'));

                        // обновляем время подгрузки файла
                        set_field_select('z_essay_submissions', 'timefinish', time(), "userid=$USER->id and assignmentid={$this->assignment->id}");

                    } else {
                        notify(get_string("uploadfailnoupdate", "assignment"));
                    }
                } else {
                    $newsubmission = $this->prepare_new_submission($USER->id);
                    $newsubmission->timemodified = time();
                    $newsubmission->numfiles = 1;
                    if (insert_record('assignment_submissions', $newsubmission)) {
                        add_to_log($this->course->id, 'assignment', 'upload',
                                'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
                        $submission = $this->get_submission($USER->id);
                        $this->update_grade($submission);
                        $this->email_teachers($newsubmission);
                        print_heading(get_string('uploadedfile'));

                        // обновляем время подгрузки файла
                        set_field_select('z_essay_submissions', 'timefinish', time(), "userid=$USER->id and assignmentid={$this->assignment->id}");
                    } else {
                        notify(get_string("uploadnotregistered", "assignment", $newfile_name) );
                    }
                }
            }
        } else {
            notify(get_string("uploaderror", "assignment")); //submitting not allowed!
        }

        print_continue('view.php?id='.$this->cm->id);

        $this->view_footer();
    }

    function setup_elements(&$mform) {
        global $CFG, $COURSE;

        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));

        $mform->addElement('select', 'resubmit', get_string("allowresubmit", "assignment"), $ynoptions);
        $mform->setHelpButton('resubmit', array('resubmit', get_string('allowresubmit', 'assignment'), 'assignment'));
        $mform->setDefault('resubmit', 0);

        $mform->addElement('select', 'emailteachers', get_string("emailteachers", "assignment"), $ynoptions);
        $mform->setHelpButton('emailteachers', array('emailteachers', get_string('emailteachers', 'assignment'), 'assignment'));
        $mform->setDefault('emailteachers', 0);

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes);
        $choices[0] = get_string('courseuploadlimit') . ' ('.display_size($COURSE->maxbytes).')';
        $mform->addElement('select', 'maxbytes', get_string('maximumsize', 'assignment'), $choices);
        $mform->setDefault('maxbytes', $CFG->assignment_maxbytes);

    }


    /**
     * Отображает первую страницу модуля с инструкцией и ссылкой на документы
     */
    function view_intro() {
        global $CFG, $USER;

        print_simple_box_start('center', '', '', 0, 'generalbox', 'intro');

        $startpage =  file_get_contents ("$CFG->dirroot/mod/assignment/type/uploadessay/startpage.html");

        // $link1 = '<strong><a href="' . $CFG->wwwroot."/file.php/1/titul_essay.pdf\">" . 'Титульный лист творческой работы</a></strong>';
        $link2 = '<strong><a href="' . $CFG->wwwroot."/file.php/1/blank_essay.pdf\">" . 'Бланк выполнения творческой работы</a></strong>';
        $vars = array(
            // '{$link1}'    => $link1,
            '{$link2}'    => $link2,
            '{$id}' => $this->cm->id,
            '{$sesskey}'  => $USER->sesskey
        );

        echo strtr($startpage, $vars);

        print_simple_box_end();
    }

    /**
     * Отображает вторую страницк модуля с иерархическим выбором темы сочинения
     */
    function select_theme_essay() {
        global $CFG, $USER;

        // print_object($CFG);
        // $CFG->debug = 'DEBUG_DEVELOPER';

        print_simple_box_start('center', '', '', 0, 'generalbox', 'intro');

        $treecss =  file_get_contents ("$CFG->dirroot/mod/assignment/type/uploadessay/tree.css");

        $tree = '<strong>Выберите тему творческой работы:</strong><ul id="essaytree">'; // class="tree"
        // $themecats = get_records('z_essay_themes');
        // print_object($themecats);
        // $themecats = get_records_sql("SELECT * from mdl_z_essay_themes") ;
        // $themecats = get_records_sql("SELECT * from mdl_course") ;
        // $themecats = get_records('course', 'category', '1', 'id', '*');


        if ($themecatsall = get_records('z_essay_themes'))  {
            $themecats = [];
            foreach ($themecatsall as $themecat) {
                if ($themecat->level == 0)
                    $themecats[] = $themecat;
            }

            // print_object($themecats);
            foreach ($themecats as $themecat) {
                // print $themecat->path . '  ' . $themecat->name . '<br>';
                $themecatname =  $themecat->path . '  ' . $themecat->name;
                $tree .= '<li class="node" style="background-image: url(../../pix/t/switch_plus.gif); background-repeat: no-repeat; background-position-y: 3px; background-position-x: 3px; ">' . '&nbsp&nbsp&nbsp&nbsp&nbsp' . "<strong>$themecatname</strong>";
                // $tree .= '<li class="node">' . "<strong>$themecatname</strong>";
                $themes = get_records_select('z_essay_themes', "parentid = $themecat->id");
                $tree .= '<ul>';
                foreach ($themes as $t) {
                    // print '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' .  $t->path . ' ' . $t->name . '<br>';
                    $themename = $t->path . ' ' . $t->name;
                    $tree .= '<li>&nbsp;&nbsp;<label><input id="btn" class="button" type="radio" name="buttonCheck" onmousedown="this.form.confirmbutton.disabled=false" value="' . $t->id . '">&nbsp;' . $themename . '</label></li>';
                }
                $tree .= '</ul>';
                $tree .= '</li>';
            }
        }
        $tree .= '</ul>';

        echo '<style> ' . $treecss . '</style>';
        echo '<form method="post" action="view.php">';
        echo '<div class="listContainer">' . $tree . '</div>';
        echo '<input type="hidden" name="id" value="' . $this->cm->id . '" />';
        echo '<input type="hidden" name="sesskey" value="' . $USER->sesskey . '" />';
        echo '<div align="center"><input type="submit" name="confirmbutton" value=" Я подтверждаю выбор темы " disabled></div>';
        echo '</form>';

        print_simple_box_end();
    }


    /**
     * Отображает 3-ю и 4-ю страницу модуля
     * @param $context
     */
    function finish_page($context) {
        global $USER;

        echo '<style>
               p.dline { line-height: 1.2;  }
              .layer1 { margin-left: 5%; margin-right: 5%;    padding: 10px;  } 
              </style>';

        print_simple_box_start('center', '', '', 0, 'generalbox', 'intro');
        if ($essay = get_record_select('z_essay_submissions', "userid=$USER->id and assignmentid={$this->assignment->id}")) {
            if ($theme = get_record_select('z_essay_themes', "id = $essay->essaythemeid")) {
                $themecat = get_record_select('z_essay_themes', "id = $theme->parentid");
                echo '<div class="layer1"><p class="dline">Выбранная тема сочинения:</p>';
                echo "<p class=\"dline\"><strong>$themecat->path $themecat->name</strong></p>";
                echo "<p class=\"dline\"><strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$theme->path $theme->name</strong></p>";
                echo "<p></p>";
                $time = date('d.m.Y H:i', $essay->timestart);
                echo "<p class=\"dline\">Дата и время начала работы над сочинением: $time</p>";
                if ($essay->timefinish > 0) {
                    $time = date('d.m.Y H:i', $essay->timefinish);
                    echo "<p class=\"dline\">Дата и время подгрузки сочинения в систему: $time</p>";
                }
                echo '</div>';
            }
        }
        print_simple_box_end();

        $filecount = $this->count_user_files($USER->id);
        // print_object($filecount);
        // если файлы уже подгружены, то выводим ссылку на файл и фразу про оценивание (4-я страница модуля)
        if ($filecount) {
            if ($submission = $this->get_submission()) {
                print_simple_box_start('center', '', '', 0, 'generalbox', 'intro');
                echo '<div class="layer1">';
                echo '<p class="dline">Подгруженное сочинение: ' .  $this->print_user_files($USER->id, true) . '</p>';
                echo '<p class="dline">Ваша работа находится на проверке, ожидайте результатов в личном кабинете Электронной приемной комиссии, либо в письме.';
                echo '</div>';
                print_simple_box_end();

                if ($submission->timemarked) {
                    $this->view_feedback();
                }
            }
        } else { // если файлы ещё не подгружены, то выводим форму (3-я страница модуля)
            // $this->view_dates();
            print_simple_box_start('center', '', '', 0, 'generalbox', 'intro');
            $submission = $this->get_submission();
            $has = has_capability('mod/assignment:submit', $context);
            // $has = true;
            // var_dump($has);
            if ($has && $this->isopen() && (!$filecount || $this->assignment->resubmit || !$submission->timemarked)) {
                $this->view_upload_form();
            }
            print_simple_box_end();
        }
    }


    /**
     *  Display all the submissions ready for grading
     */
    function display_submissions($message='') {
        global $CFG, $db, $USER;
        require_once($CFG->libdir.'/gradelib.php');

        /* first we check to see if the form has just been submitted
         * to request user_preference updates
         */
        $siteadmin = isadmin($USER->id);
        if ($USER->id == 131510) $siteadmin = true;

        if (isset($_POST['updatepref'])){
            $perpage = optional_param('perpage', 10, PARAM_INT);
            $perpage = ($perpage <= 0) ? 10 : $perpage ;
            set_user_preference('assignment_perpage', $perpage);
            set_user_preference('assignment_quickgrade', optional_param('quickgrade', 0, PARAM_BOOL));
        }

        /* next we get perpage and quickgrade (allow quick grade) params
         * from database
         */
        $perpage    = get_user_preferences('assignment_perpage', 10);

        $quickgrade = get_user_preferences('assignment_quickgrade', 0);

        $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id);

        if (!empty($CFG->enableoutcomes) and !empty($grading_info->outcomes)) {
            $uses_outcomes = true;
        } else {
            $uses_outcomes = false;
        }

        $page    = optional_param('page', 0, PARAM_INT);
        $strsaveallfeedback = get_string('saveallfeedback', 'assignment');

        /// Some shortcuts to make the code read better

        $course     = $this->course;
        $assignment = $this->assignment;
        $cm         = $this->cm;

        $tabindex = 1; //tabindex for quick grading tabbing; Not working for dropdowns yet
        add_to_log($course->id, 'assignment', 'view submission', 'submissions.php?id='.$this->cm->id, $this->assignment->id, $this->cm->id);
        $navigation = build_navigation($this->strsubmissions, $this->cm);
        print_header_simple(format_string($this->assignment->name,true), "", $navigation,
            '', '', true, update_module_button($cm->id, $course->id, $this->strassignment), navmenu($course, $cm));

        $course_context = get_context_instance(CONTEXT_COURSE, $course->id);
        if (has_capability('gradereport/grader:view', $course_context) && has_capability('moodle/grade:viewall', $course_context)) {
            echo '<div class="allcoursegrades"><a href="' . $CFG->wwwroot . '/grade/report/grader/index.php?id=' . $course->id . '">'
                . get_string('seeallcoursegrades', 'grades') . '</a></div>';
        }

        if (!empty($message)) {
            echo $message;   // display messages here if any
        }

        $context = get_context_instance(CONTEXT_MODULE, $cm->id);

        /// Check to see if groups are being used in this assignment

        /// find out current groups mode
        $groupmode = groups_get_activity_groupmode($cm);
        $currentgroup = groups_get_activity_group($cm, true);
        groups_print_activity_menu($cm, 'submissions.php?id=' . $this->cm->id);

        /// Get all ppl that are allowed to submit assignments
        if ($users = get_users_by_capability($context, 'mod/assignment:submit', 'u.id', '', '', '', $currentgroup, '', false)) {
            $users = array_keys($users);
        }

        // if groupmembersonly used, remove users who are not in any group
        if ($users and !empty($CFG->enablegroupings) and $cm->groupmembersonly) {
            if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
                $users = array_intersect($users, array_keys($groupingusers));
            }
        }

        $tablecolumns = array('picture', 'fullname', 'grade', 'submissioncomment', 'timemodified', 'timemarked', 'status', 'finalgrade');
        if ($uses_outcomes) {
            $tablecolumns[] = 'outcome'; // no sorting based on outcomes column
        }

        $tableheaders = array('',
            get_string('fullname'),
            get_string('grade'),
            get_string('comment', 'assignment'),
            get_string('lastmodified').' ('.$course->student.')',
            get_string('lastmodified').' ('.$course->teacher.')',
            get_string('status'),
            get_string('finalgrade', 'grades'));
        if ($uses_outcomes) {
            $tableheaders[] = get_string('outcome', 'grades');
        }

        require_once($CFG->libdir.'/tablelib.php');
        $table = new flexible_table('mod-assignment-submissions');

        $table->define_columns($tablecolumns);
        $table->define_headers($tableheaders);
        $table->define_baseurl($CFG->wwwroot.'/mod/assignment/submissions.php?id='.$this->cm->id.'&amp;currentgroup='.$currentgroup);

        $table->sortable(true, 'lastname');//sorted by lastname by default
        $table->collapsible(true);
        $table->initialbars(true);

        $table->column_suppress('picture');
        $table->column_suppress('fullname');

        $table->column_class('picture', 'picture');
        $table->column_class('fullname', 'fullname');
        $table->column_class('grade', 'grade');
        $table->column_class('submissioncomment', 'comment');
        $table->column_class('timemodified', 'timemodified');
        $table->column_class('timemarked', 'timemarked');
        $table->column_class('status', 'status');
        $table->column_class('finalgrade', 'finalgrade');
        if ($uses_outcomes) {
            $table->column_class('outcome', 'outcome');
        }

        $table->set_attribute('cellspacing', '0');
        $table->set_attribute('id', 'attempts');
        $table->set_attribute('class', 'submissions');
        $table->set_attribute('width', '100%');
        //$table->set_attribute('align', 'center');

        $table->no_sorting('finalgrade');
        $table->no_sorting('outcome');

        // Start working -- this is necessary as soon as the niceties are over
        $table->setup();

        if (empty($users)) {
            print_heading(get_string('nosubmitusers','assignment'));
            return true;
        }

        /// Construct the SQL

        if ($where = $table->get_sql_where()) {
            $where .= ' AND ';
        }

        if ($sort = $table->get_sql_sort()) {
            $sort = ' ORDER BY '.$sort;
        }

        // COALESCE(SIGN(SIGN(s.timemarked) + SIGN(s.timemarked - s.timemodified)), 0) AS status ';
        $select = 'SELECT u.id, u.firstname, u.lastname, u.picture, u.imagealt,
                          s.id AS submissionid, s.grade, s.submissioncomment,
                          s.timemodified, s.timemarked,
                          COALESCE(SIGN(SIGN(s.timemarked) + 
                          SIGN(
                                CASE WHEN s.timemarked = 0 THEN null 
                                WHEN s.timemarked > s.timemodified THEN s.timemarked - s.timemodified 
                                ELSE 0 END
                          )), 0) AS status ';
        $sql = 'FROM '.$CFG->prefix.'user u '.
            'LEFT JOIN '.$CFG->prefix.'assignment_submissions s ON u.id = s.userid
                                                                  AND s.assignment = '.$this->assignment->id.' '.
            'WHERE '.$where.'u.id IN ('.implode(',',$users).') ';

        $table->pagesize($perpage, count($users));

        ///offset used to calculate index of student in that particular query, needed for the pop up to know who's next
        $offset = $page * $perpage;

        $strupdate = get_string('update');
        $strgrade  = get_string('grade');
        $grademenu = make_grades_menu($this->assignment->grade);

        if (($ausers = get_records_sql($select.$sql.$sort, $table->get_page_start(), $table->get_page_size())) !== false) {
            $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id, array_keys($ausers));
            foreach ($ausers as $auser) {
                $final_grade = $grading_info->items[0]->grades[$auser->id];
                $grademax = $grading_info->items[0]->grademax;
                $final_grade->formatted_grade = round($final_grade->grade,2) .' / ' . round($grademax,2);
                $locked_overridden = 'locked';
                if ($final_grade->overridden) {
                    $locked_overridden = 'overridden';
                }

                /// Calculate user status
                $auser->status = ($auser->timemarked > 0) && ($auser->timemarked >= $auser->timemodified);
                $picture = print_user_picture($auser, $course->id, $auser->picture, false, true);

                if (empty($auser->submissionid)) {
                    $auser->grade = -1; //no submission yet
                }

                if (!empty($auser->submissionid)) {
                    ///Prints student answer and student modified date
                    ///attach file or print link to student answer, depending on the type of the assignment.
                    ///Refer to print_student_answer in inherited classes.
                    if ($auser->timemodified > 0) {
                        $studentmodified = '<div id="ts'.$auser->id.'">'.$this->print_student_answer($auser->id)
                            . userdate($auser->timemodified).'</div>';
                    } else {
                        $studentmodified = '<div id="ts'.$auser->id.'">&nbsp;</div>';
                    }
                    ///Print grade, dropdown or text
                    if ($auser->timemarked > 0) {
                        $teachermodified = '<div id="tt'.$auser->id.'">'.userdate($auser->timemarked).'</div>';

                        if ($final_grade->locked or $final_grade->overridden) {
                            $grade = '<div id="g'.$auser->id.'" class="'. $locked_overridden .'">'.$final_grade->formatted_grade.'</div>';
                        } else if ($quickgrade) {
                            $menu = choose_from_menu(make_grades_menu($this->assignment->grade),
                                'menu['.$auser->id.']', $auser->grade,
                                get_string('nograde'),'',-1,true,false,$tabindex++);
                            $grade = '<div id="g'.$auser->id.'">'. $menu .'</div>';
                        } else {
                            $grade = '<div id="g'.$auser->id.'">'.$this->display_grade($auser->grade).'</div>';
                        }

                    } else {
                        $teachermodified = '<div id="tt'.$auser->id.'">&nbsp;</div>';
                        if ($final_grade->locked or $final_grade->overridden) {
                            $grade = '<div id="g'.$auser->id.'" class="'. $locked_overridden .'">'.$final_grade->formatted_grade.'</div>';
                        } else if ($quickgrade) {
                            $menu = choose_from_menu(make_grades_menu($this->assignment->grade),
                                'menu['.$auser->id.']', $auser->grade,
                                get_string('nograde'),'',-1,true,false,$tabindex++);
                            $grade = '<div id="g'.$auser->id.'">'.$menu.'</div>';
                        } else {
                            $grade = '<div id="g'.$auser->id.'">'.$this->display_grade($auser->grade).'</div>';
                        }
                    }
                    ///Print Comment
                    if ($final_grade->locked or $final_grade->overridden) {
                        $comment = '<div id="com'.$auser->id.'">'.shorten_text(strip_tags($final_grade->str_feedback),15).'</div>';

                    } else if ($quickgrade) {
                        $comment = '<div id="com'.$auser->id.'">'
                            . '<textarea tabindex="'.$tabindex++.'" name="submissioncomment['.$auser->id.']" id="submissioncomment'
                            . $auser->id.'" rows="2" cols="20">'.($auser->submissioncomment).'</textarea></div>';
                    } else {
                        $comment = '<div id="com'.$auser->id.'">'.shorten_text(strip_tags($auser->submissioncomment),15).'</div>';
                    }
                } else {
                    $studentmodified = '<div id="ts'.$auser->id.'">&nbsp;</div>';
                    $teachermodified = '<div id="tt'.$auser->id.'">&nbsp;</div>';
                    $status          = '<div id="st'.$auser->id.'">&nbsp;</div>';

                    if ($final_grade->locked or $final_grade->overridden) {
                        $grade = '<div id="g'.$auser->id.'">'.$final_grade->formatted_grade . '</div>';
                    } else if ($quickgrade) {   // allow editing
                        $menu = choose_from_menu(make_grades_menu($this->assignment->grade),
                            'menu['.$auser->id.']', $auser->grade,
                            get_string('nograde'),'',-1,true,false,$tabindex++);
                        $grade = '<div id="g'.$auser->id.'">'.$menu.'</div>';
                    } else {
                        $grade = '<div id="g'.$auser->id.'">-</div>';
                    }

                    if ($final_grade->locked or $final_grade->overridden) {
                        $comment = '<div id="com'.$auser->id.'">'.$final_grade->str_feedback.'</div>';
                    } else if ($quickgrade) {
                        $comment = '<div id="com'.$auser->id.'">'
                            . '<textarea tabindex="'.$tabindex++.'" name="submissioncomment['.$auser->id.']" id="submissioncomment'
                            . $auser->id.'" rows="2" cols="20">'.($auser->submissioncomment).'</textarea></div>';
                    } else {
                        $comment = '<div id="com'.$auser->id.'">&nbsp;</div>';
                    }
                }

                if (empty($auser->status)) { /// Confirm we have exclusively 0 or 1
                    $auser->status = 0;
                } else {
                    $auser->status = 1;
                }

                $buttontext = ($auser->status == 1) ? $strupdate : $strgrade;

                ///No more buttons, we use popups ;-).
                $popup_url = '/mod/assignment/submissions.php?id='.$this->cm->id
                    . '&amp;userid='.$auser->id.'&amp;mode=single'.'&amp;offset='.$offset++;
                $button = link_to_popup_window ($popup_url, 'grade'.$auser->id, $buttontext, 600, 780,
                    $buttontext, 'none', true, 'button'.$auser->id);

                if (($siteadmin OR $USER->username == 'w18650' )&& $auser->timemodified > 0) {
                    $popup_url_del = '/mod/assignment/deletesubmission.php?id='.$this->cm->id
                        . '&amp;userid='.$auser->id.'&amp;mode=single'.'&amp;offset='.$offset;
                    $button_del = link_to_popup_window ($popup_url_del, 'grade'.$auser->id, 'Удалить', 300, 780,
                        'Удалить', 'none', true, 'button'.$auser->id);
                    $button .= '<br>' . $button_del;
                }

                $status  = '<div id="up'.$auser->id.'" class="s'.$auser->status.'">'.$button.'</div>';

                $finalgrade = '<span id="finalgrade_'.$auser->id.'">'.$final_grade->str_grade.'</span>';

                $outcomes = '';

                if ($uses_outcomes) {

                    foreach($grading_info->outcomes as $n=>$outcome) {
                        $outcomes .= '<div class="outcome"><label>'.$outcome->name.'</label>';
                        $options = make_grades_menu(-$outcome->scaleid);

                        if ($outcome->grades[$auser->id]->locked or !$quickgrade) {
                            $options[0] = get_string('nooutcome', 'grades');
                            $outcomes .= ': <span id="outcome_'.$n.'_'.$auser->id.'">'.$options[$outcome->grades[$auser->id]->grade].'</span>';
                        } else {
                            $outcomes .= ' ';
                            $outcomes .= choose_from_menu($options, 'outcome_'.$n.'['.$auser->id.']',
                                $outcome->grades[$auser->id]->grade, get_string('nooutcome', 'grades'), '', 0, true, false, 0, 'outcome_'.$n.'_'.$auser->id);
                        }
                        $outcomes .= '</div>';
                    }
                }

                $userlink = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $auser->id . '&amp;course=' . $course->id . '">' . fullname($auser) . '</a>';
                $row = array($picture, $userlink, $grade, $comment, $studentmodified, $teachermodified, $status, $finalgrade);
                if ($uses_outcomes) {
                    $row[] = $outcomes;
                }

                $table->add_data($row);
            }
        }

        /// Print quickgrade form around the table
        if ($quickgrade){
            echo '<form action="submissions.php" id="fastg" method="post">';
            echo '<div>';
            echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
            echo '<input type="hidden" name="mode" value="fastgrade" />';
            echo '<input type="hidden" name="page" value="'.$page.'" />';
            echo '</div>';
        }

        $table->print_html();  /// Print the whole table

        if ($quickgrade){
            $lastmailinfo = get_user_preferences('assignment_mailinfo', 1) ? 'checked="checked"' : '';
            echo '<div class="fgcontrols">';
            echo '<div class="emailnotification">';
            echo '<label for="mailinfo">'.get_string('enableemailnotification','assignment').'</label>';
            echo '<input type="hidden" name="mailinfo" value="0" />';
            echo '<input type="checkbox" id="mailinfo" name="mailinfo" value="1" '.$lastmailinfo.' />';
            helpbutton('emailnotification', get_string('enableemailnotification', 'assignment'), 'assignment').'</p></div>';
            echo '</div>';
            echo '<div class="fastgbutton"><input type="submit" name="fastg" value="'.get_string('saveallfeedback', 'assignment').'" /></div>';
            echo '</div>';
            echo '</form>';
        }
        /// End of fast grading form

        /// Mini form for setting user preference
        echo '<div class="qgprefs">';
        echo '<form id="options" action="submissions.php?id='.$this->cm->id.'" method="post"><div>';
        echo '<input type="hidden" name="updatepref" value="1" />';
        echo '<table id="optiontable">';
        echo '<tr><td>';
        echo '<label for="perpage">'.get_string('pagesize','assignment').'</label>';
        echo '</td>';
        echo '<td>';
        echo '<input type="text" id="perpage" name="perpage" size="1" value="'.$perpage.'" />';
        helpbutton('pagesize', get_string('pagesize','assignment'), 'assignment');
        echo '</td></tr>';
        echo '<tr><td>';
        echo '<label for="quickgrade">'.get_string('quickgrade','assignment').'</label>';
        echo '</td>';
        echo '<td>';
        $checked = $quickgrade ? 'checked="checked"' : '';
        echo '<input type="checkbox" id="quickgrade" name="quickgrade" value="1" '.$checked.' />';
        helpbutton('quickgrade', get_string('quickgrade', 'assignment'), 'assignment').'</p></div>';
        echo '</td></tr>';
        echo '<tr><td colspan="2">';
        echo '<input type="submit" value="'.get_string('savepreferences').'" />';
        echo '</td></tr></table>';
        echo '</div></form></div>';
        ///End of mini form
        print_footer($this->course);
    }


    /**
     * Top-level function for handling of submissions called by submissions.php
     *
     * This is for handling the teacher interaction with the grading interface
     * This should be suitable for most assignment types.
     *
     * @param $mode string Specifies the kind of teacher interaction taking place
     */
    function submissions($mode) {
        ///The main switch is changed to facilitate
        ///1) Batch fast grading
        ///2) Skip to the next one on the popup
        ///3) Save and Skip to the next one on the popup

        //make user global so we can use the id
        global $USER;

        $mailinfo = optional_param('mailinfo', null, PARAM_BOOL);
        if (is_null($mailinfo)) {
            $mailinfo = get_user_preferences('assignment_mailinfo', 0);
        } else {
            set_user_preference('assignment_mailinfo', $mailinfo);
        }

        switch ($mode) {
            case 'grade':                         // We are in a popup window grading
                if ($submission = $this->process_feedback()) {
                    //IE needs proper header with encoding
                    print_header(get_string('feedback', 'assignment').':'.format_string($this->assignment->name));
                    print_heading(get_string('changessaved'));
                    print $this->update_main_listing($submission);
                }
                close_window();
                break;

            case 'single':                        // We are in a popup window displaying submission
                $this->display_submission();
                break;

            case 'all':                          // Main window, display everything
                $this->display_submissions();
                break;

            case 'fastgrade':
                ///do the fast grading stuff  - this process should work for all 3 subclasses

                $grading    = false;
                $commenting = false;
                $col        = false;
                if (isset($_POST['submissioncomment'])) {
                    $col = 'submissioncomment';
                    $commenting = true;
                }
                if (isset($_POST['menu'])) {
                    $col = 'menu';
                    $grading = true;
                }
                if (!$col) {
                    //both submissioncomment and grade columns collapsed..
                    $this->display_submissions();
                    break;
                }

                foreach ($_POST[$col] as $id => $unusedvalue){

                    $id = (int)$id; //clean parameter name

                    $this->process_outcomes($id);

                    if (!$submission = $this->get_submission($id)) {
                        $submission = $this->prepare_new_submission($id);
                        $newsubmission = true;
                    } else {
                        $newsubmission = false;
                    }
                    unset($submission->data1);  // Don't need to update this.
                    unset($submission->data2);  // Don't need to update this.

                    //for fast grade, we need to check if any changes take place
                    $updatedb = false;

                    if ($grading) {
                        $grade = $_POST['menu'][$id];
                        $updatedb = $updatedb || ($submission->grade != $grade);
                        $submission->grade = $grade;
                    } else {
                        if (!$newsubmission) {
                            unset($submission->grade);  // Don't need to update this.
                        }
                    }
                    if ($commenting) {
                        $commentvalue = trim($_POST['submissioncomment'][$id]);
                        $updatedb = $updatedb || ($submission->submissioncomment != stripslashes($commentvalue));
                        $submission->submissioncomment = $commentvalue;
                    } else {
                        unset($submission->submissioncomment);  // Don't need to update this.
                    }

                    $submission->teacher    = $USER->id;
                    if ($updatedb) {
                        $submission->mailed = (int)(!$mailinfo);
                    }

                    $submission->timemarked = time();

                    //if it is not an update, we don't change the last modified time etc.
                    //this will also not write into database if no submissioncomment and grade is entered.

                    if ($updatedb){
                        if ($newsubmission) {
                            if (!isset($submission->submissioncomment)) {
                                $submission->submissioncomment = '';
                            }
                            if (!$sid = insert_record('assignment_submissions', $submission)) {
                                return false;
                            }
                            $submission->id = $sid;
                        } else {
                            if (!update_record('assignment_submissions', $submission)) {
                                return false;
                            }
                        }

                        // triger grade event
                        $this->update_grade($submission);

                        //add to log only if updating
                        add_to_log($this->course->id, 'assignment', 'update grades',
                            'submissions.php?id='.$this->assignment->id.'&user='.$submission->userid,
                            $submission->userid, $this->cm->id);
                    }

                }

                $message = notify(get_string('changessaved'), 'notifysuccess', 'center', true);

                $this->display_submissions($message);
                break;


            case 'next':
                /// We are currently in pop up, but we want to skip to next one without saving.
                ///    This turns out to be similar to a single case
                /// The URL used is for the next submission.

                $this->display_submission();
                break;

            case 'saveandnext':
                ///We are in pop up. save the current one and go to the next one.
                //first we save the current changes
                if ($submission = $this->process_feedback()) {
                    //print_heading(get_string('changessaved'));
                    $extra_javascript = $this->update_main_listing($submission);
                }

                //then we display the next submission
                $this->display_submission($extra_javascript);
                break;

            default:
                echo "something seriously is wrong!!";
                break;
        }
    }
}

?>