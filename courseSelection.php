<?php

# Class to provide a course selection system
require_once ('frontControllerApplication.php');
class courseSelection extends frontControllerApplication
{
	# Define the defaults; this is an override to the base class so that some can be dynamically set
	public function defaults ()
	{
		# Specify the defaults
		$defaults = array (
			'div' => 'courseselection',
			'database' => 'courseselection',
			'username' => 'courseselection',
			'password' => NULL,
			'table' => 'selections',
			'settingsTable' => 'settings',
			'settingsTableExplodeTextarea' => true,
			'administrators'	=> 'administrators',
			'webmasterContact'	=> '/contacts/webmaster.html',
			'authentication' => true,
			'tabUlClass'					=> 'tabsflat',
			'databaseAssessments' => 'assessments',	/* Note that these are only used for the lookup of people and colleges from the people database, not the main application itself */
			'userCallback'				=> NULL,		// NB Currently only a simple public function name supported
			'academicStaffCallback'		=> NULL,		// NB Currently only a simple public function name supported
			'yeargroupCallback'			=> NULL,		// NB Currently only a simple public function name supported
			'userIsDosCollegesCallback'	=> NULL,		// NB Currently only a simple public function name supported
		);
		
		# Return the defaults
		return $defaults;
	}
	
	
	# Specify additional actions
	public function actions ()
	{
		# Determine whether any outcomes can be shown
		$showOutcome = ($this->settings['IB_showoutcome'] || $this->settings['II_showoutcome'] || $this->userIsAdministrator);
		
		# Define the actions
		$actions = array (
			'submit' => array (
				'description'	=> false,
				'url'			=> 'submit.html',
				'icon'			=> 'bullet_go',
				'enableIf'		=> ($this->studentCanCurrentlySubmit ($this->yeargroup) || $this->userIsAdministrator),		// Enable the functionality if any student could currently submit, or the user is an admin
				'tab'			=> ($this->studentCanCurrentlySubmit ('IB') || $this->studentCanCurrentlySubmit ('II') ? 'Submit' : NULL),		// If the functionality is not disabled, only show the tab if any student would currently see it
			),
			'college' => array (
				'description'	=> 'Selections in your College' . (count ($this->userIsDos) > 1 ? 's' : ''),
				'url'			=> 'college.html',
				'tab'			=> 'Selections in your College' . (count ($this->userIsDos) > 1 ? 's' : ''),
				'icon'			=> 'timeline_marker',
				'enableIf'		=> ($this->userIsDos && $showOutcome),
			),
			'selections' => array (
				'description'	=> 'All selections',
				'url'			=> 'selections.html',
				'tab'			=> 'All selections',
				'icon'			=> 'application_cascade',
				'enableIf'		=> ($this->userIsStaff && $showOutcome),
			),
			'export' => array (
				'description'	=> 'Selections as CSV',
				'url'			=> 'selections.csv',
				'export'		=> true,
				'administrator'	=> true,
			),
			'capping' => array (
				'description'	=> 'Import capping allocations',
				'url'			=> 'capping.html',
				'administrator'	=> true,
				'parent'		=> 'admin',
				'subtab'		=> 'Import capping allocations',
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	
	# Database structure definition
	public function databaseStructure ()
	{
		return "
			
			-- Administrators
			CREATE TABLE `administrators` (
			  `username__JOIN__people__people__reserved` varchar(191) COLLATE utf8mb4_unicode_ci PRIMARY KEY NOT NULL COMMENT 'Username',
			  `active` enum('','Yes','No') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Yes' COMMENT 'Currently active?',
			  `privilege` enum('Administrator','Restricted administrator') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Administrator' COMMENT 'Administrator level',
			  PRIMARY KEY (`username__JOIN__people__people__reserved`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8mb4_unicode_ci COMMENT='System administrators';
			
			-- Settings
			CREATE TABLE `settings` (
			  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT COMMENT 'Automatic key',
			  `IB_opening` date NOT NULL COMMENT 'Opening date',
			  `IB_closing` date NOT NULL COMMENT 'Closing date',
			  `IB_messageHtml` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Extra introductory message',
			  `IB_required` int(2) NOT NULL COMMENT 'Selections required',
			  `IB_requiredEducation` int(2) NOT NULL COMMENT 'Selections required (Education students)',
			  `IB_maximumEducation` int(11) NOT NULL COMMENT 'Selections maximum (Education students)',
			  `IB_type` enum('checkboxes','select') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Capping?',
			  `IB_split` int(2) DEFAULT NULL COMMENT 'Split point (two unordered sets of main and other groups), if any',
			  `IB_coursenames` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Course names, one per line',
			  `IB_showoutcome` TINYINT DEFAULT NULL COMMENT 'Results now visible to students and staff?',
			  `II_opening` date NOT NULL COMMENT 'Opening date',
			  `II_closing` date NOT NULL COMMENT 'Closing date',
			  `II_messageHtml` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Extra introductory message',
			  `II_reasoning` TINYINT DEFAULT NULL COMMENT 'Include questions asking for additional info?',
			  `II_required` int(11) NOT NULL COMMENT 'Selections required',
			  `II_requiredEducation` int(11) NOT NULL COMMENT 'Selections required (Education students)',
			  `II_maximumEducation` int(11) NOT NULL COMMENT 'Selections maximum (Education students)',
			  `II_type` enum('checkboxes','select') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Capping?',
			  `II_split` int(2) DEFAULT NULL COMMENT 'Split point (two unordered sets of main and other groups), if any',
			  `II_coursenames` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Course names, one per line',
			  `II_showoutcome` TINYINT DEFAULT NULL COMMENT 'Results now visible to students and staff?',
			  `ignoreUnsubmitted` text COLLATE utf8mb4_unicode_ci COMMENT 'Students (as a list of usernames, one per line) to ignore temporarily if they have not submitted choices, to avoid capping being blocked'
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8mb4_unicode_ci COMMENT='Settings';
			
			-- Student selections
			CREATE TABLE `selections` (
			  `id` varchar(191) COLLATE utf8mb4_unicode_ci PRIMARY KEY NOT NULL COMMENT 'Key (year:username)',
			  `academicYear` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Academic year',
			  `yeargroup` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Yeargroup',
			  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Username',
			  `papers` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Selection',
			  `papersCapped` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Papers (post-capping, if relevant)',
			  `dissertation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Dissertation title',
			  `comments` varchar(400) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Reasons',
			  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Updated at'
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8mb4_unicode_ci COMMENT='Student selections';
		";
	}
	
	
	# Settings
	public function settings ($dataBindingSettingsOverrides = array ())
	{
		# Define overrides
		$dataBindingSettingsOverrides = array (
			'int1ToCheckbox' => true,
			'attributes' => array (
				'IB_opening' => array ('picker' => true, 'heading' => array ('p' => 'Course selections will open/close automatically on the dates you specify below.', 3 => 'Part IB settings'), ),
				'II_opening' => array ('picker' => true, 'heading' => array (3 => 'Part II settings'), ),
				'IB_closing' => array ('picker' => true, ),
				'II_closing' => array ('picker' => true, ),
				'IB_type' => array ('type' => 'radiobuttons', 'values' => array ('checkboxes' => 'No capping', 'select' => 'Capping')),
				'II_type' => array ('type' => 'radiobuttons', 'values' => array ('checkboxes' => 'No capping', 'select' => 'Capping')),
				'IB_coursenames' => array ('cols' => 60, 'rows' => 15, 'picker' => true, 'regexp' => '^([0-9]+): (.+)$', 'description' => 'Each line must begin with the course number followed by a colon and a space',),
				'II_coursenames' => array ('cols' => 60, 'rows' => 15, 'picker' => true, 'regexp' => '^([0-9]+): (.+)$', 'description' => 'Each line must begin with the course number followed by a colon and a space',),
				'ignoreUnsubmitted' => array ('picker' => true, 'heading' => array (3 => 'Other settings'), 'cols' => 20, 'rows' => 5, ),
			),
		);
		
		# Run the main settings system with the overriden attributes
		return parent::settings ($dataBindingSettingsOverrides);
	}
	
	
	# Extra processing, pre-actions
	public function mainPreActions ()
	{
		# Determine if the user is a DoS
		$this->userIsDos = $this->userIsDos ();	// Returns a list of colleges for which the user is a DoS
		
		# Get current staff
		$staff = $this->getAcademicStaff ();
		$this->userIsStaff = ($this->userIsDos || isSet ($staff[$this->user]) || $this->userIsAdministrator);
		
		# Set the current academic year
		require_once ('timedate.php');
		$this->academicYear = timedate::academicYear (5, true);		// May
		$this->academicYearStart = substr ($this->academicYear, 0, 4);
		
		# Define the yeargroups and their identifiers, using the year offsets from the current academic year, to determine the starting year cohort
		$yeargroups = array (
			'IB' => 'undergraduate' . ($this->academicYearStart - 1),		// E.g. 2020-21 second year uses undergraduate2019
			'II' => 'undergraduate' . ($this->academicYearStart - 2),		// E.g. 2020-21 third year uses undergraduate2018
		);
		
		# Get the students, including education students; these will be in username order
		$this->students = array ();
		$this->educationStudents = array ();
		foreach ($yeargroups as $yeargroup => $yeargroupId) {
			$this->students[$yeargroup]				= $this->getYeargroup ($yeargroupId);
			$this->educationStudents[$yeargroup]	= $this->getYeargroup ($yeargroupId . 'education');
		}
		
		# Rearrange the course data as lists rather than strings
		$this->convertCourseSettings ();
		
		# If the user is an admin, and a user has been specified by the URL, submit as that user
		if ($this->userIsAdministrator) {
			if (isSet ($_GET['user'])) {
				$this->user = $_GET['user'];
			}
		}
		
		# Assign the student's year group
		$this->yeargroup = false;
		foreach ($this->students as $yeargroup => $students) {
			if (in_array ($this->user, $students)) {
				$this->yeargroup = $yeargroup;
				break;
			}
		}
		
		# Determine whether capping data is present for either yeargroup
		$this->cappingDataPresent = $this->cappingDataPresent ();
	}
	
	
	# Determine whether the current user can currently submit
	private function studentCanCurrentlySubmit ($yeargroup)
	{
		# End if no yeargroup
		if (!$yeargroup) {return false;}
		
		# Check based on the native opening times
		$opening = strtotime ($this->settings[$yeargroup . '_opening'] . ' 00:00:00');
		$closing = strtotime ($this->settings[$yeargroup . '_closing'] . ' 23:59:59');
		$now = time ();
		$isOpen = ($now >= $opening && $now < $closing);
		return $isOpen;
	}
	
	
	
	# Extra processing
	public function main ()
	{
		
	}
	
	
	
	# Function to split the course setting block into a list
	private function convertCourseSettings ()
	{
		# On the settings page itself, do nothing
		if ($this->action == 'settings') {return;}
		
		# Do this for each group
		foreach ($this->students as $yeargroup => $students) {
			
			# Rearrange as paper1=>name, 2=>name, etc. for each group
			$courses = array ();
			foreach ($this->settings[$yeargroup . '_coursenames'] as $course) {
				list ($key, $name) = explode (': ', $course, 2);
				$courses[$key] = $key . ': ' . $name;
			}
			
			# Replace the current array
			$this->settings[$yeargroup . '_coursenames'] = $courses;
		}
	}
	
	
	# Home page
	public function home ()
	{
		# Introduction, only hidden if the user is a student and outcomes are not yet visible
		$showPersonalAllocations = (!$this->yeargroup || ($this->yeargroup && $this->settings[$this->yeargroup . '_showoutcome']));
		if (!$showPersonalAllocations) {
			echo "\n<p>This system is for students to select courses intended to be taken in the coming academic year ({$this->academicYear}).</p>";
		}
		
		# Section for students
		if ($this->yeargroup) {	// i.e. if student
			
			# Show the allocations if available
			if ($this->settings[$this->yeargroup . '_showoutcome']) {
				echo $this->showPersonalAllocations ();
				return true;
			}
			
			# Show the submission page link (or a message that the system is not yet open or has now closed)
			if ($this->studentCanCurrentlySubmit ($this->yeargroup)) {
				echo "\n<h2>Submit choices</h2>";
				echo "\n<p>Firstly please read the <a href=\"/undergraduate/courseguide/" . str_replace ('-20', '-', $this->academicYear) . '/' . ($this->yeargroup == 'IB' ? 'part1b/' : 'part2/') . "\">Course Guide for Part {$this->yeargroup} " . $this->academicYear . "</a> (for students going into the " . ($this->yeargroup == 'IB' ? 'second' : 'third') . " year).</p>";
				if ($data = $this->getUserSubmission ()) {
					echo "\n<p>You can <a href=\"submit.html\"><strong>update your selection</strong></a>.</p>";
				} else {
					echo "\n<p>Then <a href=\"submit.html\"><strong>submit your selection</strong></a>.</p>";
				}
			} else {
				$notYetOpen = (time () < strtotime ($this->settings[$this->yeargroup . '_opening'] . ' 00:00:00'));
				if ($notYetOpen) {
					echo "\n<p>Submission of course choices is not yet open. The Undergraduate Office will circulate an e-mail when submission of course choices opens.</p>";
				} else {
					echo "\n<p>Submission of course choices has now closed. The Undergraduate Office will circulate an e-mail when course choices are confirmed, and they will then be listed here.</p>";
				}
			}
			return true;
		}
		
		# End if not in any year group (and not a DoS/staff)
		if (!$this->userIsDos && !$this->userIsStaff) {
			echo "\n<p>You do not appear to be in either year group, so have no access to this system. If you believe this is wrong, please <a href=\"{$this->baseUrl}/feedback.html\">contact us</a>.</p>";
			return false;
		}
		
		# For admins, provide a way to submit/view choices as a student
		if ($this->userIsAdministrator) {
			echo "\n<h2>Submit/view choices</h2>";
			echo "\n<p>As an admin, you can view an existing selection by, or submit on behalf of, a student:</p>";
			echo $this->userSelector ();
		}
		
		# DoS view, shown only if enabled
		if (isSet ($this->actions['college'])) {
			echo "\n<h2>{$this->actions['college']['description']}</h2>";
			echo "\n<p>As a Director of Studies, you can <a href=\"{$this->baseUrl}/college.html\">view the summary of submissions by College</a>.</p>";
		}
		
		# Staff view, shown only if enabled
		if (isSet ($this->actions['selections'])) {
			echo "\n<h2>All selections by students</h2>";
			echo "\n<p>As an member of staff, you can <a href=\"{$this->baseUrl}/selections.html\">view all submissions made by the students</a>.</p>";
		}
	}
	
	
	# Function to show the allocations for a college or set of colleges
	public function college ()
	{
		# Start the HTML
		$html  = "\n<p>As a DoS, you can view the allocations to each student:</p>";
		
		# Get the list of colleges for this Dos
		$colleges = $this->userIsDos;
		
		# Add the allocations, limited to the colleges
		$data = $this->getSelections ('collegeId, yeargroup, surname, forename', array_keys ($colleges));
		
		# Ensure an entry is created for each college and yeargroup
		$selections = array ();
		foreach ($colleges as $collegeId => $collegeName) {
			$selections[$collegeId] = array ();
		}
		
		# Reindex by college, yeargroup then name
		foreach ($data as $selection) {
			$collegeId = $selection['collegeId'];
			$yeargroup = $selection['yeargroup'];
			$username = $selection['username'];
			$selections[$collegeId][$yeargroup][$username] = $selection;
		}
		
		# Construct a jumplist of colleges present in the selection data
		$jumplist = array ();
		foreach ($colleges as $collegeId => $collegeName) {
			$jumplist[$collegeId] = "<a href=\"#{$collegeId}\">{$collegeName}</a>";
		}
		$jumplist = application::htmlUl ($jumplist, 0, 'small');
		
		# Convert the data into HTML
		$html  = "\n<p>As a DoS, you can view the course selections for each student " . (count ($colleges) == 1 ? 'in the College' : 'in each College') . ", which will appear as they are submitted.</p>";
		$choicesBeingOrdered = array ();
		foreach ($this->students as $yeargroup => $ignored) {
			if ($this->settings[$yeargroup . '_type'] == 'select') {
				if (!$this->cappingDataPresent[$yeargroup]) {	// For yeargroups where students specify an ordering, the ordering is only relevant until capping is done
					$choicesBeingOrdered[] = 'Part ' . $yeargroup;
				}
			}
		}
		if ($choicesBeingOrdered) {
			$html .= "\n<p>The course choices for " . implode (' and ', $choicesBeingOrdered) . ' are shown in the order selected by the student.</p>';
		}
		
		# If a yeargroup is not enabled, say so at the top
		foreach ($this->students as $yeargroup => $ignored) {
			if (!$this->settings["{$yeargroup}_showoutcome"]) {
				$html .= "\n<div class=\"graybox\">";
				$html .= "\n\t<p class=\"notyetvisible\"><em><strong>Note:</strong> The details for <strong>Part {$yeargroup}</strong> are not yet visible to staff, as per the <a href=\"{$this->baseUrl}/settings.html\">settings</a>." . ($this->userIsAdministrator ? ' <strong>However</strong>, you can see these as you are an Administrator.' : '') . "</em></p>";
				$html .= "\n</div>";
			}
		}
		
		$html .= $jumplist;
		foreach ($selections as $collegeId => $yeargroups) {
			$collegeName = $colleges[$collegeId];
			$html .= "\n<h2 id=\"{$collegeId}\">{$collegeName}:</h2>";
			
			# Loop through each yeargroup in the settings
			foreach ($this->students as $yeargroup => $ignored) {
				
				# Skip this year if required
				if (!$this->settings["{$yeargroup}_showoutcome"] && !$this->userIsAdministrator) {continue;}
				
				$html .= "\n<h3>Part {$yeargroup}:</h3>";
				
				#!# Actually needs to show all students in the college - not just those submitted
				if (!isSet ($yeargroups[$yeargroup])) {
					$html .= "<p><em>No {$collegeName} students submitted for Part {$yeargroup}.</em></p>";
					continue;
				}
				
				# Loop through each student
				$students = $yeargroups[$yeargroup];
				foreach ($students as $student => $selection) {
					
					# Show each paper
					$html .= "\n<h4>{$selection['forename']} {$selection['surname']} ({$selection['username']})</h4>";
					$papers = explode (',', $selection['papers']);
					if ($this->cappingDataPresent[$yeargroup]) {
						sort ($papers);
					}
					$courseNames = array ();
					foreach ($papers as $paper) {	// Loop through each paper in the settings
						$courseNames[$paper] = 'Paper ' . $this->settings[$yeargroup . '_coursenames'][$paper];
					}
					$html .= application::htmlUl ($courseNames);
				}
			}
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to show the allocations
	private function showPersonalAllocations ()
	{
		# Get the data for this student
		$data = $this->getSelections ('yeargroup, surname, forename, college', array (), $this->user);
		
		# Compile the HTML
		$html  = "<h2>Course allocation</strong></h2>";
		$html .= "<p>Name: <strong>" . htmlspecialchars ("{$data['forename']} {$data['surname']}") . '</strong></p>';
		$html .= "<p>College: <strong>{$data['college']}</strong></p>";
		$html .= "<p>You have been allocated to the following courses:</p>";
		$html .= $this->allocationsListFormatted ($data['papers'], $this->yeargroup);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to turn a student's allocations into a formatted list
	private function allocationsListFormatted ($papers, $yeargroup)
	{
		# Convert to list and sort
		$papers = explode (',', $papers);
		sort ($papers);
		
		# Show the results
		$list = array ();
		foreach ($papers as $paper) {
			$courseName = $this->settings[$yeargroup . '_coursenames'][$paper];
			$list[$paper] = "<strong>Paper {$courseName}</strong>";
		}
		
		# Compile the HTML
		$html = application::htmlUl ($list, 0, 'spaced');
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to determine if the user is a DoS
	private function userIsDos ()
	{
		# Get the data; for Administrators, return all colleges
		$limitToUser = ($this->userIsAdministrator ? false : $this->user);
		if (!$colleges = $this->getUserIsDosColleges ($limitToUser)) {return array ();}
		
		# Return the colleges
		return $colleges;
	}
	
	
	# Function to get the data of a user
	private function getUserSubmission ()
	{
		# Get the data
		$key = $this->submissionKey ($this->user);
		$data = $this->databaseConnection->selectOne ($this->settings['database'], $this->settings['table'], array ('id' => $key));
		
		# Return the data
		return $data;
	}
	
	
	# Function to return the unique key for this user
	private function submissionKey ($username)
	{
		return $this->academicYear . ':' . $username;		// e.g. '2019-2020:spqr1'
	}
	
	
	
	# Function to create a form
	public function submit ()
	{
		# Start the HTML
		$html = '';
		
		# End if no year group found
		if (!$this->yeargroup) {
			$html .= "\n" . '<p class="warning">You do not appear to be in the list of students.</p>';
			$html .= "\n" . "<p>If you believe this is an error, please <a href=\"{$this->settings['webmasterContact']}\">contact the Webmaster</a> urgently to resolve the situation.</p>";
			echo $html;
			return false;
		}
		
		# Get the details for this student
		if (!$this->userInfo = $this->getUser ($this->user)) {
			$html .= $this->reportError ("Could not retrieve details for {$this->user} at\n{$_SERVER['_PAGE_URL']}");
			echo $html;
			#!# Mail admin
			return false;
		}
		
		# Determine if the facility is open
		$this->settings['opening'] = $this->settings[$this->yeargroup . '_opening'] . ' 00:00:00';
		$this->settings['closing'] = $this->settings[$this->yeargroup . '_closing'] . ' 23:59:59';
		$facilityIsOpen = $this->facilityIsOpen ($html, false, "\n<p class=\"warning\">Please contact the Undergraduate Office and your Director of Studies.</p>");
		
		# If the user is an admin, enable it to be open
		if ($this->userIsAdministrator) {
			if (!$facilityIsOpen) {
				$html  = "\n<p class=\"success\">You are running this as student <strong>{$this->user}</strong> [<a href=\"{$this->baseUrl}/\">change this?</a>]. This facility is otherwise closed to students.</p>";
				$facilityIsOpen = true;
			}
		}
		
		# End if the facility is not open
		if (!$facilityIsOpen) {
			echo $html;
			return;
		}
		
		# Start with opening text, including a special message if set
		$html .= "\n<p>The form below is for selection of papers for students <strong>going into</strong> Part {$this->yeargroup}.</p>";
		$html .= "\n<p><em>Deadline for selection: end of " . date ('l jS F Y', strtotime ($this->settings['closing'])) . ".</em></p>";
		if ($this->settings[$this->yeargroup . '_messageHtml']) {
			$html .= $this->settings[$this->yeargroup . '_messageHtml'];
		}
		
		# Deny admins making changes once any capping is loaded
		if ($this->cappingDataPresent[$this->yeargroup]) {
			$html .= "\n<p class=\"warning\">Changes cannot now be made for this Part {$this->yeargroup} student because capping data is present.</p>";
			echo $html;
			return;
		}
		
		# Set the number of choices that can be submitted
		$choices = $this->settings[$this->yeargroup . '_required'];
		
		# If the user is an education student, change the number of selections they are required to make
		$isEducationStudent = false;
		if (in_array ($this->user, $this->educationStudents[$this->yeargroup])) {
			$isEducationStudent = true;
			$this->settings[$this->yeargroup . '_required'] = $this->settings[$this->yeargroup . '_requiredEducation'];	// Overwrite
			$choices = $this->settings[$this->yeargroup . '_maximumEducation'];
		}
		
		# Create a form
		$form = new form (array (
			'displayRestrictions' => false,
			'mailAdminErrors' => true,
			'reappear' => true,
			'div' => 'ultimateform horizontalonly leftlabels',
			'nullText' => '',
			'unsavedDataProtection' => true,
		));
		
		# Obtain any current submission
		$data = $this->getUserSubmission ();
		
		# Determine current selections
		$papers = ($data ? explode (',', $data['papers']) : array ());
		
		# Add a heading
		$headingText = "\n<h3>Please select {$this->settings[$this->yeargroup . '_required']}" . ($isEducationStudent && $choices != $this->settings[$this->yeargroup . '_requiredEducation'] ? '-' . $this->settings[$this->yeargroup . '_maximumEducation'] : '') . ($choices == 1 ? ' choice' : ' choices') . ":</h3>";
		
		# Add an explanatory heading for select groups
		if ($this->settings[$this->yeargroup . '_type'] == 'select') {
			$headingText .= "\n<p>";
			$headingText .= "The course titles listed below are those that will be offered in Part {$this->yeargroup} in " . $this->academicYear . '.';
			if ($this->settings[$this->yeargroup . '_type'] == 'select') {
				if (!$this->settings[$this->yeargroup . '_split']) {
					$headingText .= '<br />Please rank your choice of courses in order of preference.';
				}
				$headingText .= '<br />Please note that course choices cannot be guaranteed when a particular course is heavily subscribed.';
			}
			$headingText .= '</p>';
		}
		$form->heading ('', $headingText);
		
		# Switch between checkbox and select (set of "1st choice", "2nd choice", etc. choices, for each of the courses) format
		switch ($this->settings[$this->yeargroup . '_type']) {
			
			case 'checkboxes':
				$form->checkboxes (array (
					'title'		=> 'Papers',
					'name'		=> 'papers',
					'values'	=> $this->settings[$this->yeargroup . '_coursenames'],
					'required'	=> $this->settings[$this->yeargroup . '_required'],
					'default'	=> $papers,
				));
				break;
				
			case 'select':
				
				$widgetNames = array ();
				for ($choice = 1; $choice <= $choices; $choice++) {
					
					# If a split point is specified, treat this as two unordered sets of main and other groups
					if ($this->settings[$this->yeargroup . '_split']) {
						if ($choice == 1) {
							$form->heading (4, 'Main choices (not in any order)');
						}
						if ($choice == $this->settings[$this->yeargroup . '_split']) {
							$form->heading (4, 'Other choices (not in any order)');
						}
						if ($choice < $this->settings[$this->yeargroup . '_split']) {
							$choiceLabel = 'Main choice';
						} else {
							$choiceLabel = 'Other choice';
						}
					} else {
						switch ($choice) {
							case 1:		$choiceLabel = '1st choice';			break;
							case 2:		$choiceLabel = '2nd choice';			break;
							case 3:		$choiceLabel = '3rd choice';			break;
							default:	$choiceLabel = $choice . 'th choice';	break;
						}
					}
					
					$widgetNames[$choice] = 'choice' . $choice;
					$form->select (array (
						'name'      => $widgetNames[$choice],
						'title'     => $choiceLabel,
						'values'    => $this->settings[$this->yeargroup . '_coursenames'],
						'default'	=> (($papers && isSet ($papers[$choice - 1])) ? $papers[$choice - 1] : false),
						'required'	=> ($isEducationStudent && ($choice > $this->settings[$this->yeargroup . '_requiredEducation']) ? false : true),
					));
				}
				
				# Ensure the values are unique
				if (count ($widgetNames) > 1) {	#!# This shouldn't really be necessary - the form should gracefully degrade this option
					$form->validation ('different', $widgetNames);
				}
				
				break;
		}
		
		# Add a special message if set
		#!# This is very hard-coded
		if (array_key_exists ($this->yeargroup . '_reasoning', $this->settings)) {	// II_reasoning exists in the settings, but not IB_reasoning
			if ($this->settings[$this->yeargroup . '_reasoning']) {
				$form->heading (3, 'Additional information');
				
				#!# If there are no courses (e.g. a student transferring in) then this will disrupt the spreadsheet order
				$expectedCoursesTaken = 4;	// This is only needed because of students who are joining at Part II, and therefore didn't have any courses in Part IB. Without this, the alignment of the CSV file becomes wrong.
				$courses = $this->getCurrentCoursesIb ();
				for ($i = 0; $i < $expectedCoursesTaken; $i++) {
					$form->input (array (
						'name'		=> 'course' . ($i + 1),
						'title'		=> 'Current IB course ' . ($i + 1),
						'default'	=> (isSet ($courses[$i]['title']) ? $courses[$i]['title'] : '?/-'),
						'editable'	=> false,
						'discard'	=> true,
					));
				}
				$form->input (array (
					'name'		=> 'dissertation',
					'title'		=> 'My submitted dissertation title is',
					'required'	=> true,
					'size'		=> 60,
					'default'	=> ($data ? $data['dissertation'] : false),
				));
				$form->textarea (array (
					'name'		=> 'comments',
					'title'		=> 'Reasons for choosing one or more of these courses (optional).',	// <br />[NB these comments will <strong>only</strong> be used in cases where the ranking of paper selections does not lead to a unique assignment of students to individual papers]',
					'cols'		=> 60,
					'rows'		=> 4,
					'maxlength'	=> 400,		// NB Ensure the table structure has a matching VARCHAR length
					'default'	=> ($data ? $data['comments'] : false),
				));
			}
		}
		
		# Process the form and produce the results
		if ($result = $form->process ($html)) {
			
			# Show a tick to indicate a successful form
			$tick  = "\n<div class=\"graybox\">";
			$tick .= "\n\t<p class=\"success\">{$this->tick} <strong>Your selections have been submitted, as shown below.</strong></p>";
			$tick .= "\n</div>";
			$html = $tick . $html;
			
			# Inject fixed data
			$result['id'] = $this->submissionKey ($this->user);
			$result['username'] = $this->user;
			$result['academicYear'] = $this->academicYear;
			$result['yeargroup'] = $this->yeargroup;
			$result['updatedAt'] = date ('Y-m-d H:i:s');	// #!# NOW() doesn't seem to work
			
			# Combine the options for select
			$papers = array ();
			if ($this->settings[$this->yeargroup . '_type'] == 'select') {
				foreach ($widgetNames as $widgetName) {
					if ($result[$widgetName]) {		// Exclude empty, which can happen with Education students were required != maximum
						$papers[] = $result[$widgetName];
					}
					unset ($result[$widgetName]);
				}
			} else {	// i.e. checkboxes
				foreach ($result['papers'] as $paperId => $selected) {
					if ($selected) {
						$papers[] = $paperId;
					}
				}
			}
			$result['papers'] = implode (',', $papers);
			
			# Save the data, replacing any current data (using ON DUPLICATE KEY UPDATE)
			if (!$this->databaseConnection->insert ($this->settings['database'], $this->settings['table'], $result, true)) {
				$html = "\n<p class=\"warning\">{$this->cross} There was a problem saving your choices. Please contact the Webmaster.</p>";
			}
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to create a user switcher
	private function userSelector ()
	{
		# Take no action if a user is already selected
		if (isSet ($_GET['user']) && strlen ($_GET['user'])) {return false;}
		
		# Start the HTML
		$html = '';
		
		# Create a listing of students indexed by username
		$students = array ();
		foreach ($this->students as $yeargroup => $studentsThisYear) {
			foreach ($studentsThisYear as $index => $student) {
				$students[$yeargroup][$student] = $student;
				if (in_array ($student, $this->educationStudents[$yeargroup])) {
					$students[$yeargroup][$student] .= ' [Education student]';
				}
			}
		}
		
		# Create a form
		$form = new form (array (
			'name' => 'users',
			'formCompleteText' => false,
			'display' => 'template',
			'displayTemplate' => '{[[PROBLEMS]]} {user} {[[SUBMIT]]}',
			'requiredFieldIndicator' => false,
			'submitButtonAccesskey' => false,
			'submitButtonText' => 'Go!',
		));
		$form->select (array ( 
		    'name'				=> 'user',
		    'title'				=> 'Username',
		    'values'			=> $students,
		    'required'			=> true,
			'onchangeSubmit'	=> true,
		));
		if ($result = $form->process ($html)) {
			
			# Redirect
			$url = $_SERVER['_SITE_URL'] . $this->baseUrl . '/submit.html?user=' . $result['user'];
			$html .= application::sendHeader (302, $url, true);
		}
		
		# Return the HTML
		return $html;
	}
	
	# Function to get current courses
	private function getCurrentCoursesIb ()
	{
		# Get the data for this student
		#!# This should be replaced by an API call to a service
		$academicYear = ($this->academicYearStart - 1) . '-' . $this->academicYearStart;
		$query = "SELECT
			id,title,
			 LPAD(paper,100,'0') AS paperNumberNatsorted	/* 100 should be safe to get full sorting! */
		FROM
		{$this->settings['databaseAssessments']}.courses
		WHERE
			`year` = '{$academicYear}'
			AND yeargroup = 'IB'
			AND (
				paper IN (
					SELECT paper FROM {$this->settings['databaseAssessments']}.entries
					WHERE
						crsid = '{$this->user}'
						AND `year` = '{$academicYear}'
						AND paper REGEXP '^([0-9]+)$'
				)
			)
		ORDER BY paperNumberNatsorted, type, title, entries
		;";
		$data = $this->databaseConnection->getData ($query);
		
		# Return the data
		return $data;
	}
	
	
	# Determine if capping data is present
	private function cappingDataPresent ()
	{
		# Get the totals
		$cappingDataPresent = array ();
		foreach ($this->students as $yeargroup => $ignore) {
			if ($this->settings[$yeargroup . '_type'] == 'select') {
				$total = $this->databaseConnection->getTotal ($this->settings['database'], $this->settings['table'], $restrictionSql = "WHERE papersCapped IS NOT NULL AND academicYear = '{$this->academicYear}' AND yeargroup = '{$yeargroup}'");
			} else {
				$total = false;
			}
			$cappingDataPresent[$yeargroup] = $total;
		}
		
		# Return the result
		return $cappingDataPresent;
	}
	
	
	# Function to compile the responses
	public function selections ()
	{
		# Start the HTML
		$selectionsHtml = '';
		
		# Get the data
		$data = $this->getSelections ();
		
		# Split papers out (field is comma-separated)
		$data = $this->databaseConnection->splitSetToMultipleRecords ($data, 'papers');
		
		# Regroup by year
		$data = application::regroup ($data, 'yeargroup', false);
		
		# If a yeargroup is not enabled, say so at the top
		foreach ($this->students as $yeargroup => $ignored) {
			if (!$this->settings["{$yeargroup}_showoutcome"]) {
				$selectionsHtml .= "\n<div class=\"graybox\">";
				$selectionsHtml .= "\n\t<p class=\"notyetvisible\"><em><strong>Note:</strong> The details for <strong>Part {$yeargroup}</strong> are not yet visible to staff, as per the <a href=\"{$this->baseUrl}/settings.html\">settings</a>." . ($this->userIsAdministrator ? ' <strong>However</strong>, you can see these as you are an Administrator.' : '') . "</em></p>";
				$selectionsHtml .= "\n</div>";
			}
		}
		
		# Loop through each year group
		$jumplist = array ();
		foreach ($this->students as $yeargroup => $students) {	// Loop through the definition, so that all year groups are definitely present
			
			# Skip if not showing
			if (!$this->settings["{$yeargroup}_showoutcome"] && !$this->userIsAdministrator) {continue;}
			
			# Heading
			$text = "Part {$yeargroup} - " . ($this->cappingDataPresent[$yeargroup] ? 'allocation of students to courses' : 'courses chosen by students');
			$selectionsHtml .= "\n\n<h3 class=\"spaced\" id=\"part{$yeargroup}\">{$text}</h3>";
			
			# Define the jumplist entry
			$jumplist[] = "Jump to: <a href=\"#part{$yeargroup}\">{$text}</a>";
			
			# End if no selections so far
			if (!$data || (!$selections = $data[$yeargroup])) {
				$selectionsHtml .= "\n<p>There have not yet been any submissions for the year group " . $this->academicYear . '.</p>';
				continue;
			}
			
			# Add a warning if required
			if ($this->cappingDataPresent[$yeargroup]) {
				$selectionsHtml .= "\n<div class=\"warningbox\">";
				$selectionsHtml .= "\n\n<p class=\"warning\"><strong>This section shows the allocations of students to courses, rather than the students' original selections.</strong></p>\n";
				$selectionsHtml .= "\n</div>";
			}
			
			# Initialise the list of choices, which ensures all are listed in the right order, even if no students have (yet) picked this one
			$chosen = array ();
			foreach ($this->settings[$yeargroup . '_coursenames'] as $courseKey => $courseName) {
				$chosen[$courseKey] = array ();
			}
			
			# Loop through each submission and each course in the submission
			foreach ($selections as $selection) {
				$user = $selection['username'];
				$courseKey = $selection['papers'];
				$chosen[$courseKey][$user] = "{$selection['forename']} {$selection['surname']} ({$selection['college']})";
			}
			
			# Determine the list of students who have submitted
			$studentsSubmitted = array ();
			foreach ($selections as $selection) {
				$studentsSubmitted[] = $selection['username'];
			}
			$studentsSubmitted = array_unique ($studentsSubmitted);
			
			# Determine the total number of submissions
			$totalSubmissions = count ($selections);
			
			# Show the summary table
			$selectionsHtml .= $this->chosenSummaryTable ($yeargroup, $chosen, $totalSubmissions, $studentsSubmitted);
			
			# Show unsubmitted students
			$selectionsHtml .= $this->unsubmittedStudentsList ($yeargroup);
			
			# Show the list of users for this year group
			foreach ($chosen as $courseKey => $studentsChosen) {
				$course = $this->settings[$yeargroup . '_coursenames'][$courseKey];
				$total = count ($studentsChosen);
				$selectionsHtml .= "\n\n<h4>Paper " . htmlspecialchars ($course) . " &nbsp; &mdash; total: {$total}</h4>";
				ksort ($studentsChosen);
				$selectionsHtml .= application::htmlTableKeyed ($studentsChosen, array (), false, 'lines compressed small', false, $showColons = false);
			}
		}
		
		# Start the HTML
		$html  = '';
		
		# Add the jumplist
		if (count ($jumplist) > 1) {	// No point showing if only one
			$html .= application::htmlUl ($jumplist);
		}
		
		# Export links, if the user is an admin
		if ($this->userIsAdministrator) {
			$exportLinks = array ();
			foreach ($this->students as $yeargroup => $ignore) {
				$exportLinks[] = "<a href=\"{$this->baseUrl}/selections-" . strtolower ($yeargroup) . ".csv\">Part {$yeargroup}</a>";
			}
			$exportLink = "\n<p>" . $this->icon ('page_excel') . 'Download raw data for: ' . implode (' and ', $exportLinks) . '.</p>';
			$html .= $exportLink;
		}
		
		# Add the selections
		$html .= $selectionsHtml;
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to get the selections
	private function getSelections ($orderBy = 'yeargroup, surname, forename, college', $colleges = array (), $username = false)
	{
		# Get the data
		#!# This should be partly replaced by an API call to a service
		$query = "SELECT
			{$this->settings['table']}.*,
			people.forename,
			people.surname,
			colleges.id AS collegeId,
			colleges.college
		FROM {$this->settings['database']}.{$this->settings['table']}
		LEFT OUTER JOIN people.people ON {$this->settings['table']}.username = people.people.username 
		LEFT OUTER JOIN people.colleges ON people.college__JOIN__people__colleges__reserved = people.colleges.id
		WHERE
			    academicYear = '{$this->academicYear}'
			AND active = 'Y'"	// Do not include students no longer in the Department, i.e. have transferred to other Departments
			. ($colleges ? " AND colleges.id IN('" . implode ("','", $colleges) . "')" : '')
			. ($username ? " AND {$this->settings['table']}.username = '{$username}'" : '') . "
		ORDER BY {$orderBy}
		;";
		if (!$data = $this->databaseConnection->getData ($query, "{$this->settings['database']}.{$this->settings['table']}")) {return array ();}
		
		# Replace papers with the papersCapped data if required allocations mode has been enabled for this yeargroup
		foreach ($data as $key => $entry) {
			if ($this->cappingDataPresent[$entry['yeargroup']]) {
				$data[$key]['papers'] = $entry['papersCapped'];
			}
			unset ($entry['papersCapped']);
		}
		
		# Flatten if a username (i.e. a single record) has been specified)
		if ($username) {
			foreach ($data as $key => $user) {
				$data = $user;
				break;
			}
		}
		
		# Return the data
		return $data;
	}
	
	
	# Function to create a summary table for each yeargroup
	private function chosenSummaryTable ($yeargroup, $chosen, $totalSubmissions, $studentsSubmitted)
	{
		# Start the HTML
		$html = '';
		
		# Compile the totals
		$summary = array ();
		foreach ($chosen as $courseKey => $students) {
			$course = 'Paper ' . $this->settings[$yeargroup . '_coursenames'][$courseKey];
			$summary[$course] = count ($students);
		}
		
		# Determine the total number of users
		$totalUsers = count ($studentsSubmitted);
		
		# Add the HTML for this year group
		if (!$this->cappingDataPresent[$yeargroup]) {
			$html .= "\n\n<p>There have been <strong>{$totalSubmissions}</strong> selections by a total of <strong>{$totalUsers}</strong> Part {$yeargroup} students (out of a possible total of " . count ($this->students[$yeargroup]) . " students), an average of <strong>" . round (($totalSubmissions / $totalUsers), 1) . '</strong> selections per student. Totals are as follows:</p>';
		}
		$html .= application::htmlTableKeyed ($summary, array (), false, 'lines compressed');
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to show unsubmitted students
	private function unsubmittedStudentsList ($yeargroup)
	{
		# Get students who have not yet submitted
		$unsubmittedStudents = $this->getUnsubmittedStudents ($yeargroup);
		
		# End if none
		if (!$unsubmittedStudents) {return false;}
		
		# Assemble the HTML
		$html = "\n" . '<p class="warning">The following ' . (count ($unsubmittedStudents) == 1 ? 'student has' : 'students have') . ' not yet submitted their choices: ' . $this->studentsLinkedList ($unsubmittedStudents) . '</strong>.</p>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to determine students who have not yet submitted
	#!# Note that this is rather inefficient, as it recomputes the getSelections data which in some circumstances is already known
	private function getUnsubmittedStudents ($yeargroup)
	{
		# Get the data
		$data = $this->getSelections ();
		
		# Determine the students who have submitted
		$studentsSubmitted = array ();
		if ($data) {
			
			# Regroup by yeargroup
			if ($data = application::regroup ($data, 'yeargroup', false)) {
				
				# Get the selections for this yeargroup
				$selections = $data[$yeargroup];
				
				# Determine the students who have submitted
				$studentsSubmitted = array ();
				foreach ($selections as $selection) {
					$studentsSubmitted[] = $selection['username'];
				}
				$studentsSubmitted = array_unique ($studentsSubmitted);
			}
		}
		
		# Get the list of unsubmitted students by comparing with all students in the yeargroup
		$unsubmittedStudents = array_diff ($this->students[$yeargroup], $studentsSubmitted, $this->settings['ignoreUnsubmitted']);
		
		# Return the list of unsubmitted students
		return $unsubmittedStudents;
	}
	
	
	# Function to create a hyperlinked list of usernames
	public function studentsLinkedList ($usernames)
	{
		# Compile the list
		$links = array ();
		foreach ($usernames as $username) {
			$links[] = "<a href=\"{$this->baseUrl}/submit.html?user={$username}\"><strong>{$username}</strong></a>";
		}
		
		# Compile the HTML
		$html = implode (',', $links);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to import capping allocations
	public function capping ()
	{
		# Start the HTML
		$html = '';
		
		# Determine the yeargroups involving capping
		$yeargroups = array ();
		foreach ($this->students as $yeargroup => $students) {
			if ($this->settings[$yeargroup . '_type'] == 'select') {
				$yeargroups[$yeargroup] = 'Part ' . $yeargroup . ($this->cappingDataPresent[$yeargroup] ? ' (Replace current capping data)' : '');
			}
		}
		
		# End if no yeargroups
		if (!$yeargroups) {
			$html .= "\n<p>No years required students to make an ordered choice, so importing of capping is not enabled.</p>";
			echo $html;
			return;
		}
		
		# If there are unsubmitted students, deny upload, as this will mean that a student present in the spreadsheet will have no data to UPDATE
		foreach ($yeargroups as $yeargroup => $description) {
			$unsubmittedStudentsList = $this->unsubmittedStudentsList ($yeargroup);
			if ($unsubmittedStudentsList) {
				$html .= $unsubmittedStudentsList;
				$html .= "\n<p class=\"warning\">Import of data for part {$yeargroup} is not yet possible as there are students who have not yet submitted choices.</p>";
				unset ($yeargroups[$yeargroup]);
			}
		}
		
		# End if no yeargroups now
		if (!$yeargroups) {
			echo $html;
			return;
		}
		
		# Get the first yeargroup
		$yeargroupsValues = array_keys ($yeargroups);
		$yeargroupFirst = reset ($yeargroupsValues);
		
		# Create a form
		$form = new form (array (
			'formCompleteText' => false,
			'reappear'	=> true,
			'div' => 'ultimateform capping',
		));
		$form->heading ('p', 'Note: you can come back here at any time to update (replace) the data.');
		$form->heading ('p', "Example, showing what the spreadsheet should look like. At the top are the paper numbers as paper1, paper2, etc. The cells below are markers when the student has selected that course - these can contain anything (the system will only check if the cell contains anything or not). Highlight the data from your spreadsheet, then copy and paste into the form below:<br /><img src=\"{$this->baseUrl}/images/capping.png\" width=\"90%\" alt=\"Capping data example\" />");
		$form->radiobuttons (array (
			'name'		=> 'yeargroup',
			'title'		=> 'Yeargroup',
			'required'	=> true,
			'values'	=> $yeargroups,
			'default'	=> $yeargroupFirst,
		));
		$prefix = 'paper';
		$form->textarea (array (
			'name'		=> 'data',
			'title'		=> "Paste in your spreadsheet contents, as per the example above.<br /><br />Fields must be specified in first line, as: <tt>username</tt> <tt>paper1</tt> <tt>paper2</tt><br />etc.",
			'required'	=> true,
			'rows'		=> 15,
			'cols'		=> 80,
			'autofocus'	=> (count ($yeargroupsValues) == 1),
			'wrap'		=> 'off',
		));
		
		# Do checks on the pasted data
		require_once ('csv.php');
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			if ($unfinalisedData['yeargroup'] && $unfinalisedData['data']) {
				
				# Arrange the data as a TSV (or end if faulty)
				if (!$data = csv::tsvToArray ($unfinalisedData['data'], true, $firstColumnIsIdIncludeInData = true, $errorMessage)) {
					$form->registerProblem ('faultydata', $errorMessage);
				} else {
					
					# Ensure there is some data
					if (count ($data) < 2) {
						$form->registerProblem ('data', 'There must be at least one line of data in the pasted spreadsheet block.');
					}
					
					# Get the expected headers (course numbers) as defined in the settings
					$expectedHeaders = array ('username');
					foreach ($this->settings[$unfinalisedData['yeargroup'] . '_coursenames'] as $courseKey => $courseName) {
						$expectedHeaders[] = $prefix . $courseKey;
					}
					
					# Get the fields (headers) in the data
					$usersValues = array_keys ($data);
					$usersFirst = reset ($usersValues);
					$headersPresent = array_keys ($data[$usersFirst]);
					
					# Ensure the headings are all present
					if ($headersPresent !== $expectedHeaders) {
						$form->registerProblem ('headers', 'The headers in the pasted spreadsheet block must be exactly <strong><tt>' . implode ('</tt>, <tt>', $expectedHeaders) . '</tt></strong>. Please double-check there are no extra spaces around these words.');
					}
					
					# Ensure all the students are present in the data
					$allStudents = $this->students[$unfinalisedData['yeargroup']];
					$studentsInData = array_keys ($data);
					sort ($studentsInData);
					if ($allStudents !== $studentsInData) {
						
						# Check for missing students
						if ($missingStudents = array_diff ($allStudents, $studentsInData)) {
							$form->registerProblem ('studentsmissing', 'The following students are not present: ' . $this->studentsLinkedList ($missingStudents));
						}
						
						# Check for extra students
						if ($extraStudents = array_diff ($studentsInData, $allStudents)) {
							$form->registerProblem ('studentsadded', 'The following students have been added but did not originally submit any data, so must be added via the submission side: ' . $this->studentsLinkedList ($extraStudents));
						}
					}
				}
			}
		}
		
		# Process the form or end
		if (!$result = $form->process ($html)) {
			echo $html;
			return false;
		}
		
		# Compile the data into a list of capped papers
		$allocations = array ();
		foreach ($data as $username => $papers) {
			$chosenPapers = array ();	// Start with empty string
			foreach ($papers as $key => $value) {
				if ($key == 'username') {continue;}	// Skip this field
				$value = trim ($value);
				if (strlen ($value)) {	// i.e. if selected (i.e. any string in the cell, usually a number or string like 'paper3')
					$paperNumber = preg_replace ('/^' . $prefix . '/', '', $key);	// I.e. 'paper3' becomes 3
					$chosenPapers[$value] = $paperNumber;
				}
			}
			ksort ($chosenPapers);
			$id = $this->submissionKey ($username);
			$allocations[$id] = array ('papersCapped' => implode (',', $chosenPapers));
		}
		
		# Clear all existing data for this yeargroup and year; in theory this shouldn't be required since the number of students matches (and if students are deleted after a paper capping setting, they aren't relevant anymore anyway)
		$query = "UPDATE {$this->settings['database']}.{$this->settings['table']} SET papersCapped = NULL WHERE academicYear = '{$this->academicYear}' AND yeargroup = '{$result['yeargroup']}';";
		$this->databaseConnection->query ($query);
		
		# Update the entries
		if (!$this->databaseConnection->updateMany ($this->settings['database'], $this->settings['table'], $allocations)) {
			$html = $this->reportError ("There was a problem saving the capping data:\n\n" . print_r ($this->databaseConnection->error (), true), 'There was a problem saving the capping data. The Webmaster has been informed and will investigate shortly.');
			echo $html;
			return false;
		}
		
		# Confirm success, resetting all previous HTML
		$html  = "\n<div class=\"graybox\">";
		$html .= "\n\t<p class=\"success\">{$this->tick} <strong>The capping data has been saved.</strong></p>";
		if ($this->settings["{$result['yeargroup']}_showoutcome"]) {
			$html .= "\n\t<p class=\"success\">{$this->tick} You can <a href=\"{$this->baseUrl}/selections.html#part{$result['yeargroup']}\">view this on the selections page</a>.</p>";
		} else {
			$html .= "\n\t<p class=\"warning\"><img src=\"/images/icons/exclamation.png\" alt=\"!\" class=\"icon\" /> However, allocations are not yet viewable to students or staff. You need to <a href=\"{$this->baseUrl}/settings.html\">enable this in the settings</a>.</p>";
		}
		$html .= "\n</div>";
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to export the data
	public function export ()
	{
		# Ensure the year group is specified
		if (!isSet ($_GET['yeargroup'])) {
			$this->page404 ();
			return false;
		}
		
		# Get the data
		$rawdata = $this->getSelections ('yeargroup, surname, forename, college');
		
		# Regroup by yeargroup
		$rawdata = application::regroup ($rawdata, 'yeargroup');
		
		# Ensure the year group is valid
		$yeargroup = strtoupper ($_GET['yeargroup']);
		if (!isSet ($rawdata[$yeargroup])) {
			$this->page404 ();
			return false;
		}
		$rawdata = $rawdata[$yeargroup];
		
		# Exclude unwanted fields
		$unwantedFields = array ('id', 'academicYear', 'collegeId');
		if (!array_key_exists (strtoupper ($yeargroup) . '_reasoning', $this->settings) || !$this->settings[$yeargroup . '_reasoning']) {	// Regarding the first-half clause, II_reasoning exists in the settings, but not IB_reasoning
			$unwantedFields[] = 'dissertation';
			$unwantedFields[] = 'comments';
		}
		foreach ($rawdata as $id => $selection) {
			foreach ($unwantedFields as $unwantedField) {
				unset ($rawdata[$id][$unwantedField]);
			}
		}
		
		# Expand the papers part of the data, keeping the same ordering of fields in each record for convenience
		$data = array ();
		foreach ($rawdata as $id => $selection) {
			foreach ($selection as $field => $value) {
				if ($field == 'papers') {
					$papers = explode (',', $value);
					for ($i = 0; $i < $this->settings[$yeargroup . '_required']; $i++) {
						$label = ($this->settings[$yeargroup . '_type'] == 'select' ? 'Choice' : 'Selection') . ' ' . ($i + 1);
						$data[$id][$label] = (isSet ($papers[$i]) ? $papers[$i] : '');
					}
					$choices = array ();
					$i = 0;
					foreach ($papers as $courseKey) {
						$i++;
						$choices[$courseKey] = $i;	// e.g. choice for paper (course) 2 is 1st
					}
					foreach ($this->settings[$yeargroup . '_coursenames'] as $courseKey => $courseName) {
						$courseName = 'Paper ' . $courseName;
						if ($this->settings[$yeargroup . '_type'] == 'select') {
							$data[$id][$courseName] = (isSet ($choices[$courseKey]) ? $choices[$courseKey] : '');
						} else {
							$data[$id][$courseName] = (in_array ($courseKey, $papers) ? 'Y' : '');
						}
					}
				} else {
					$data[$id][$field] = $value;
				}
			}
		}
		
		# Set the header labels
		$headerLabels = $this->databaseConnection->getHeadings ($this->settings['database'], $this->settings['table']);
		$headerLabels += $this->databaseConnection->getHeadings ('people', 'colleges');
		
		# Serve the CSV
		require_once ('csv.php');
		csv::serve ($data, $filenameBase = strtolower ("selections-{$yeargroup}"), $timestamp = true, $headerLabels);
	}
	
	
	# Function to get the user
	private function getUser ($userId)
	{
		# Get the data and return it
		$callbackFunction = $this->settings['userCallback'];
		$data = $callbackFunction ($this->databaseConnection, $userId);
		return $data;
	}
	
	
	# Function to get a list of academic staff
	private function getAcademicStaff ()
	{
		# Get the data and return it
		$callbackFunction = $this->settings['academicStaffCallback'];
		$academicStaff = $callbackFunction ($this->databaseConnection);
		return $academicStaff;
	}
	
	
	# Function to get the year group of students
	private function getYeargroup ($yeargroupId)
	{
		# Get the data and return it
		$callbackFunction = $this->settings['yeargroupCallback'];
		$yeargroup = $callbackFunction ($this->databaseConnection, $yeargroupId);
		return $yeargroup;
	}
	
	
	# Function to determine if the user is a DoS, returning a list of their colleges if so
	private function getUserIsDosColleges ($userId /* or false for all */)
	{
		# Get the data and return it
		$callbackFunction = $this->settings['userIsDosCollegesCallback'];
		$dosList = $callbackFunction ($this->databaseConnection, $userId);
		return $dosList;
	}
}

?>
