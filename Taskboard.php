<?php

	class Taskboard {
		const TASK_OPEN = 0;
		const TASK_CLOSED = 1;

		public $task_lifespan = 30;		// Days after the last bump in a task for it to disapear from searches

		public function __construct(){
		}

		// CREATE NEW TASK
		public function createTask($tripcode, $title, $message, $tags){
			// Create the task
			$data = array(
				'created' => time(),
				'bumped' => time(),
				'title' => $title,
				'message' => $message,
				'tripcode' => $tripcode,
				'status' => $this::TASK_OPEN
			);

			$task_id = Database::insert('tasks', $data);
			if(!$task_id) return false;


			// Create the tags
			$sql_tags = array();
			foreach($tags as $t){
				$t = (string)$t;
				if(empty($t)) continue;

				$sql_tags = array(
					'label' => $t,
					'task_id' => $task_id
				);
				// TODO: These values should really be in 1 insert query
				Database::insert('tags', $sql_tags);
			}
			
			return $task_id;
		}



		// LOOK FOR RANDOM TASK
		public function randomTasks($limit=50){
			
		}
		
		// DELETE a task by either ID or Tripcode
		public function delTaskBy($delType, $input=array()){
			if(!is_array($input)) $input = array(); // Input array is always zero
			
			switch($delType){
				case 'Delete a post':	// $input <-- post ID
					$s_id = $input[0];
					
								$sql[] = <<<SQL
											DELETE FROM tasks 
												WHERE 
													id=$s_id														
SQL;
					break;
					
				case 'Delete all post by trip':	// $input <-- Tripcode name ##DANGER## This will delete everything done by a poster
					$s_pass = $input[0];
					
								$sql[] = <<<SQL
											DELETE FROM tasks 
												WHERE 
													tripcode=$s_pass														
SQL;
					break;
					
				case 'Delete single task with normal password': // $input <-- Task ID, Task Password
					$s_ID = $input[0] ;
					$s_pass = __tripCode($input[1]) ;
					
								$sql[] = <<<SQL
											DELETE FROM tasks 
												WHERE 
													id=$s_ID
													AND
													tripcode='$s_pass'														
SQL;
					break;
				default:
					echo '\n No action taken as there was an unknown delete option chosen for delTaskBy()\n';
			}
			try{
				foreach($sql as $s){
					Database::query($s);
					echo 'Delete command sent';
				}
			}
			catch(PDOException $e){
				echo $e;
				echo 'Delete Operation failed, did you get your password wrong?';
			}
		
			
		}
		
		// RETURN 1 TASK BY TASK ID
		public function getTaskByID($id=''){

			// btw '?' in sql is basically the biggest number allowed in SQL
			$sql = <<<SQL
							SELECT
								DISTINCT tasks.id AS task_id, tasks.tripcode, tasks.created, tasks.bumped, tasks.title, tasks.message
							FROM tasks
							WHERE
								tasks.id = $id
							LIMIT 1
SQL;
			
			try {
				$rs = Database::query($sql);
			} catch (Eception $e){
				return array();
			}
			
			// If something failed.. return no tasks
			if(!$rs) return array();

			return $rs;
		
		}

		
		
		

		// GET A LIST OF TASK (OPTIONAL TAG SEARCH)
		public function getTasks($tags=array(), $limit=50){
			if(!is_array($tags)) $tags = array();

			$sql_tag_labels = array();
			foreach($tags as $t){
				$tmp = preg_replace("/[^a-zA-Z0-9_\- ]/i", "", $t);
				if(!empty($tmp)) $sql_tag_labels[] = $tmp;
			}

			if(!empty($sql_tag_labels)){
				$sql_where_tags = "AND tags.label IN ('".implode("','", $sql_tag_labels)."')";
			} else {
				$sql_where_tags = '';
			}
			
			$sql = <<<SQL
					SELECT
						DISTINCT tasks.id AS task_id, tasks.tripcode, tasks.created, tasks.bumped, tasks.title, tasks.message
					FROM tasks
					INNER JOIN tags ON tasks.id = tags.task_id
					WHERE
						tasks.status = ?
						$sql_where_tags
						AND tasks.bumped > ?

					ORDER BY tasks.bumped DESC
					LIMIT ?
SQL;
			
			try {
				$rs = Database::query($sql, array($this::TASK_OPEN, time() - strtotime('-'.$this->task_lifespan.' days'), $limit));
			} catch (Eception $e){
				return array();
			}
			
			// If something failed.. return no tasks
			if(!$rs) return array();

			// TODO: Get the tags for each task!

			return $rs;
		}



		// RETURNS ARRAY OF FREQENTLY USED TAGS
		public function topTags($limit=5){
			$rs = Database::query("SELECT label, COUNT(*) as count FROM tags GROUP BY label ORDER BY count DESC LIMIT ?", array($limit));
			return $rs;
		}



		// INIT DATABASE
		public function initDatabase(){
			$sql = array();
			$sql[] = <<<SQL
						CREATE TABLE IF NOT EXISTS tasks ( 
							id INTEGER PRIMARY KEY,
							tripcode VARCHAR(25),
							status INTEGER ,
							created INTEGER ,
							bumped INTEGER ,
							title VARCHAR(100),
							message TEXT
						);
SQL;
			
			$sql[] = <<<SQL
						CREATE TABLE IF NOT EXISTS tags ( 
							task_id INTEGER,
							label VARCHAR(50)
						);
SQL;
			
			$sql[] = <<<SQL
						CREATE TABLE IF NOT EXISTS messages (
							id INTEGER PRIMARY KEY,
							task_id NOT NULL,
							user_id INTEGER,
							created INT,
							msg_type VARCHAR(25),    
							title VARCHAR(25),
							message VARCHAR(25)
						);
SQL;
			
			foreach($sql as $s){
				Database::query($s);
			}
		}
	}