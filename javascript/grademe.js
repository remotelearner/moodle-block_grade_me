/*
 * Collapse/Expand all courses/assessments. If we are in the course,
 * then only collapse/expand all assessments.
 */
function togglecollapseall(iscoursecontext) {
    if($('dl').hasClass('expanded')) {
        $('.toggle').removeClass('open');
        if (!iscoursecontext) {
            $('dd').addClass('block_grade_me_hide');
        }
        $('dd ul').addClass('block_grade_me_hide');
        $('dl').removeClass('expanded');
    } else {
        $('.toggle').addClass('open');
        if (!iscoursecontext) {
            $('dd').removeClass('block_grade_me_hide');
        }
        $('dd ul').removeClass('block_grade_me_hide');
        $('dl').addClass('expanded');
    }
}
