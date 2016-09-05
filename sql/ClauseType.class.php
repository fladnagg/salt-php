<?php 
/**
 * ClauseType class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    salt\sql
 */
namespace salt;

/**
 * List of Clause type (for register binds by source clauses)
 */
class ClauseType {
	
	/** Special key : match all clauses for retrieve only */
	const ALL='ALL';
	
	/** Clause SELECT */
	const SELECT='SELECT';
	/** Clause WHERE */
	const WHERE='WHERE';
	/** Clause JOIN */
	const JOIN='JOIN';
	/** Clause ORDER BY */
	const ORDER='ORDER';
	/** Clause GROUP BY */
	const GROUP='GROUP';
	/** Clause LIMIT */
	const LIMIT='LIMIT';

	/** Clause SET (Update query) */
	const SET='SET';
	/** Clause INSERT (Insert query) */
	const INSERT='INSERT';
}