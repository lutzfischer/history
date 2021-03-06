<?php
	session_start();
    include('./utils.php');
	//you could comment out the following line and have no login authentication. 
	ajaxBootOut();

	include('../../connectionString.php');
	//open connection
	$dbconn = pg_connect($connectionString)
		or die('Could not connect: ' . pg_last_error());
		
    //error_log (print_r ($_SESSION, true));
    //error_log (print_r ($_POST, true));
	$searches = $_POST["searches"];
    $userRights = getUserRights ($dbconn, $_SESSION['user_id']);

    $qPart1 = "SELECT search.id, search.notes, user_name as user_name, search.submit_date AS submit_date, search.name AS name, search.status AS status, search.random_id AS random_id, array_agg(sequence_file.file_name) AS file_name
        FROM search
        INNER JOIN users on search.uploadedby = users.id
        INNER JOIN search_sequencedb on search.id = search_sequencedb.search_id
        INNER JOIN sequence_file on search_sequencedb.seqdb_id = sequence_file.id 
        WHERE "
    ;

    // if can_see_all but not a superuser insert this clause
    $qPart2 = "((COALESCE (search.private, FALSE) = FALSE AND COALESCE (users.hidden, FALSE) = FALSE) OR search.uploadedby = $1) AND";

    $qPart3 = "
        COALESCE (search.hidden, FALSE) = FALSE
        GROUP BY search.id, user_name
        ORDER BY (CASE WHEN status = 'queuing' THEN 0 WHEN is_executing THEN 1 ELSE 2 END) ASC, search.id DESC ;"
    ;
	
	if (!$userRights["isSuperUser"] || $searches == "MINE") {
     $privateClause = ($searches == "MINE" ? "search.uploadedby = $1 AND " : $qPart2);
		  pg_prepare($dbconn, "my_query", $qPart1.$privateClause.$qPart3);
		  $result = pg_execute($dbconn, "my_query", [$_SESSION['user_id']]);
	} else {
     pg_prepare($dbconn, "my_query", $qPart1.$qPart3);
     $result = pg_execute($dbconn, "my_query", []);
    }

    echo json_encode (array("user"=>$_SESSION['session_name'], "userRights"=>$userRights, "data"=>pg_fetch_all($result)));

    //close connection
    pg_close($dbconn);
?>
