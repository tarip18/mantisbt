<?php
# MantisBT - A PHP based bugtracking system

# MantisBT is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# MantisBT is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Mantis Webservice Tests
 *
 * @package Tests
 * @subpackage UnitTests
 * @copyright Copyright MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 */

require_once 'RestBase.php';

/**
 * Test fixture for user update webservice methods.
 *
 * @requires extension curl
 * @group REST
 */
class RestUserTests extends RestBase {
	/**
	 * @var array List of user ids to delete in tearDown()
	 */
	private $usersToDelete = array();

	/**
	 * Setup test fixture
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();
	}

	/**
	 * Test /users/me API which users use to get information about themselves.
	 */
	public function testGetCurrentUser() {
		$t_response = $this->builder()->get( '/users/me' )->send();
		$this->assertEquals( 200, $t_response->getStatusCode() );

		$t_user = json_decode( $t_response->getBody(), true );
		$this->assertTrue( isset( $t_user['id'] ) );
		$this->assertTrue( is_numeric( $t_user['id'] ) );
		$this->assertTrue( isset( $t_user['name'] ) );
		$this->assertEquals( 'english', $t_user['language'] );
		$this->assertEquals( 'America/Los_Angeles', $t_user['timezone'] );
		$this->assertTrue( is_numeric( $t_user['access_level']['id'] ) );
		$this->assertTrue( isset( $t_user['access_level']['name'] ) );
		$this->assertTrue( isset( $t_user['access_level']['label'] ) );
		$this->assertGreaterThanOrEqual( 1, count( $t_user['projects'] ) );
		$this->assertTrue( isset( $t_user['projects'][0]['id'] ) );
		$this->assertTrue( isset( $t_user['projects'][0]['name'] ) );
	}

	/**
	 * Test creating a user as an anonymous user
	 */
	public function testCreateUserAnonymous() {
		$t_user_to_create = array(
			'name' => Faker::username()
		);

		$t_response = $this->builder()->post( '/users', $t_user_to_create )->anonymous()->send();
		$this->deleteUserIfCreated( $t_response );
		$this->assertEquals( 401, $t_response->getStatusCode() );
	}

	/**
	 * Test the use of POST /users to create users with just a username
	 *
	 * @dataProvider providerValidUserNames
	 */
	public function testCreateUserMinimal( $p_username ) {
		$t_user_to_create = array(
			'name' => Faker::username()
		);

		$t_response = $this->builder()->post( '/users', $t_user_to_create )->send();
		$this->deleteUserIfCreated( $t_response );
		$this->assertEquals( 201, $t_response->getStatusCode() );

		$t_body = json_decode( $t_response->getBody(), true );
		$this->assertTrue( isset( $t_body['user'] ) );

		$t_user = $t_body['user'];
		$this->assertTrue( isset( $t_user['id'] ) );
		$this->assertTrue( is_numeric( $t_user['id'] ) );
		$this->assertEquals( $t_user_to_create['name'], $t_user['name'] );
		$this->assertEquals( 'english', $t_user['language'] );
		$this->assertEquals( 'America/Los_Angeles', $t_user['timezone'] );
		$this->assertEquals( 25, $t_user['access_level']['id'] );
		$this->assertEquals( "reporter", $t_user['access_level']['name'] );
		$this->assertEquals( "reporter", $t_user['access_level']['label'] );
		$this->assertGreaterThanOrEqual( 1, count( $t_user['projects'] ) );
		$this->assertTrue( isset( $t_user['projects'][0]['id'] ) );
		$this->assertTrue( isset( $t_user['projects'][0]['name'] ) );
	}

	/**
	 * Test the use of POST /users to create users with all supported fields
	 */
	public function testCreateUserFull() {
		$t_user_to_create = array(
			'name' => Faker::username(),
			'real_name' => Faker::realname(),
			'email' => Faker::email(),
			'password' => Faker::password(),
			'access_level' => array( "name" => "developer" ),
			'protected' => false,
			'enabled' => false,
		);

		$t_response = $this->builder()->post( '/users', $t_user_to_create )->send();
		$this->deleteUserIfCreated( $t_response );
		$this->assertEquals( 201, $t_response->getStatusCode() );

		$t_body = json_decode( $t_response->getBody(), true );
		$this->assertTrue( isset( $t_body['user'] ) );

		$t_user = $t_body['user'];
		$this->assertTrue( isset( $t_user['id'] ) );
		$this->assertTrue( is_numeric( $t_user['id'] ) );
		$this->assertEquals( $t_user_to_create['name'], $t_user['name'] );
		$this->assertEquals( $t_user_to_create['access_level']['name'], $t_user['access_level']['name'] );
		$this->assertEquals( $t_user_to_create['access_level']['name'], $t_user['access_level']['label'] );
		$this->assertGreaterThanOrEqual( 1, count( $t_user['projects'] ) );
		$this->assertTrue( isset( $t_user['projects'][0]['id'] ) );
		$this->assertTrue( isset( $t_user['projects'][0]['name'] ) );

		# TODO: test protected, enabled, language and timezone
	}

	/**
	 * Test creating users with duplicate usernames
	 */
	public function testCreateUserDuplicateUsername() {
		$t_user_to_create = array(
			'name' => Faker::username()
		);

		$t_response = $this->builder()->post( '/users', $t_user_to_create )->send();
		$this->deleteUserIfCreated( $t_response );
		$this->assertEquals( 201, $t_response->getStatusCode() );

		$t_response = $this->builder()->post( '/users', $t_user_to_create )->send();
		$this->assertEquals( 400, $t_response->getStatusCode() );
	}

	/**
	 * Test getting an existing user by id.
	 */
	public function testGetUserById() {
		$t_user_to_create = array(
			'name' => Faker::username()
		);

		$t_response = $this->builder()->post( '/users', $t_user_to_create )->send();
		$t_user_id = $this->deleteUserIfCreated( $t_response );
		$this->assertEquals( 201, $t_response->getStatusCode() );

		$t_response = $this->builder()->get( '/users/' . $t_user_id )->send();
		$this->assertEquals( 200, $t_response->getStatusCode() );

		$t_user = json_decode( $t_response->getBody(), true );
		$this->assertTrue( isset( $t_user['id'] ) );
		$this->assertTrue( is_numeric( $t_user['id'] ) );
		$this->assertEquals( $t_user_to_create['name'], $t_user['name'] );
		$this->assertEquals( 'english', $t_user['language'] );
		$this->assertEquals( 'America/Los_Angeles', $t_user['timezone'] );
		$this->assertEquals( 25, $t_user['access_level']['id'] );
		$this->assertEquals( "reporter", $t_user['access_level']['name'] );
		$this->assertEquals( "reporter", $t_user['access_level']['label'] );
		$this->assertGreaterThanOrEqual( 1, count( $t_user['projects'] ) );
		$this->assertTrue( isset( $t_user['projects'][0]['id'] ) );
		$this->assertTrue( isset( $t_user['projects'][0]['name'] ) );
	}

	/**
	 * Test getting a non-existent user by id.
	 */
	public function testGetUserByIdAnonymous() {
		$t_user_to_create = array(
			'name' => Faker::username()
		);

		$t_response = $this->builder()->post( '/users', $t_user_to_create )->send();
		$t_user_id = $this->deleteUserIfCreated( $t_response );
		$this->assertEquals( 201, $t_response->getStatusCode() );

		$t_response = $this->builder()->get( '/users/' . $t_user_id )->anonymous()->send();
		$this->assertEquals( 401, $t_response->getStatusCode() );
	}

	/**
	 * Test getting a non-existent user by id.
	 */
	public function testGetUserByIdNotFoundAnonymous() {
		$t_response = $this->builder()->get( '/users/1000000' )->anonymous()->send();
		$this->assertEquals( 401, $t_response->getStatusCode() );
	}

	/**
	 * Test getting a non-existent user by id.
	 */
	public function testGetUserByIdNotFound() {
		$t_response = $this->builder()->get( '/users/1000000' )->send();
		$this->assertEquals( 404, $t_response->getStatusCode() );
	}

	/**
	 * Test getting a non-existent user by id.
	 */
	public function testGetUserByIdZeroAnonymous() {
		$t_response = $this->builder()->get( '/users/0' )->anonymous()->send();
		$this->assertEquals( 401, $t_response->getStatusCode() );
	}

	/**
	 * Test getting a user by id zero
	 */
	public function testGetUserByIdZero() {
		$t_response = $this->builder()->get( '/users/0' )->send();
		$this->assertEquals( 400, $t_response->getStatusCode() );
	}

	/**
	 * Test getting a user by a negative id
	 */
	public function testGetUserByIdNegative() {
		$t_response = $this->builder()->get( '/users/-1' )->send();
		$this->assertEquals( 400, $t_response->getStatusCode() );
	}

	/**
	 * Test delete an existing user by id.
	 */
	public function testDeleteUserById() {
		$t_user_to_create = array(
			'name' => Faker::username()
		);

		$t_response = $this->builder()->post( '/users', $t_user_to_create )->send();
		$t_user_id = $this->deleteUserIfCreated( $t_response );
		$this->assertEquals( 201, $t_response->getStatusCode() );

		$t_response = $this->builder()->get( '/users/' . $t_user_id )->send();
		$this->assertEquals( 200, $t_response->getStatusCode() );

		$t_response = $this->builder()->delete( '/users/' . $t_user_id )->send();
		$this->assertEquals( 204, $t_response->getStatusCode() );

		$t_response = $this->builder()->get( '/users/' . $t_user_id )->send();
		$this->assertEquals( 404, $t_response->getStatusCode() );
	}

	/**
	 * Test delete an existing user by id.
	 */
	public function testDeleteUserByIdAnonymous() {
		$t_user_to_create = array(
			'name' => Faker::username()
		);

		$t_response = $this->builder()->post( '/users', $t_user_to_create )->send();
		$t_user_id = $this->deleteUserIfCreated( $t_response );
		$this->assertEquals( 201, $t_response->getStatusCode() );

		$t_response = $this->builder()->get( '/users/' . $t_user_id )->send();
		$this->assertEquals( 200, $t_response->getStatusCode() );

		$t_response = $this->builder()->delete( '/users/' . $t_user_id )->anonymous()->send();
		$this->assertEquals( 401, $t_response->getStatusCode() );

		$t_response = $this->builder()->get( '/users/' . $t_user_id )->send();
		$this->assertEquals( 200, $t_response->getStatusCode() );
	}

	/**
	 * Test deleting a non-existent user by id.
	 */
	public function testDeleteUserByIdNotFound() {
		$t_response = $this->builder()->delete( '/users/1000000' )->send();
		$this->assertEquals( 204, $t_response->getStatusCode() );
	}

	/**
	 * Test deleting a user by id zero.
	 */
	public function testDeleteUserByIdZero() {
		$t_response = $this->builder()->delete( '/users/0' )->send();
		$this->assertEquals( 400, $t_response->getStatusCode() );
	}

	/**
	 * Test deleting the current logged in user.
	 */
	public function testDeleteCurrentUser() {
		$t_response = $this->builder()->get( '/users/me' )->send();
		$this->assertEquals( 200, $t_response->getStatusCode() );
		$t_user = json_decode( $t_response->getBody(), true );
		$t_user_id = $t_user['id'];

		$t_response = $this->builder()->delete( '/users/' . $t_user_id )->send();
		$this->assertEquals( 400, $t_response->getStatusCode() );
	}

	/**
	 * Test deleting the current logged in user (anonymous).
	 */
	public function testDeleteCurrentUserAnonymous() {
		$t_response = $this->builder()->get( '/users/me' )->send();
		$this->assertEquals( 200, $t_response->getStatusCode() );
		$t_user = json_decode( $t_response->getBody(), true );
		$t_user_id = $t_user['id'];

		# if anonymous login enabled, this will not give 401
		# TODO: adapt / test with different settings for anonymous login
		$t_response = $this->builder()->delete( '/users/' . $t_user_id )->send();
		$this->assertEquals( 400, $t_response->getStatusCode() );
	}

	/**
	 * @dataProvider providerInvalidUserNames
	 */
	public function testCreateUserInvalidUsername( $p_username ) {
		$t_user_to_create = array(
			'name' => $p_username
		);

		$t_response = $this->builder()->post( '/users', $t_user_to_create )->send();
		$this->deleteUserIfCreated( $t_response );
		$this->assertEquals( 400, $t_response->getStatusCode() );
	}

	/**
	 * Provides a set of invalid usernames
	 *
	 * @return array test cases
	 */
	public function providerInvalidUserNames() {
		return array(
			'blank_spaces' => array( ' ' ),
			'blank_tabs' => array( "\t" ),
			'empty' => array( '' ),
			'numeric' => array( '1234' ),
			'integer' => array( 1234 ),
			'too_long' => array( Faker::randStr( 500 ) )
		);
	}

	/**
	 * Providers a set of valid usernames
	 *
	 * @return array test cases
	 */
	public function providerValidUserNames() {
		return array(
			'regular' => array( Faker::username() ),
			'with_spaces_in_middle' => array( "some user" ),
			'email' => array( 'vboctor@somedomain.com' ),
			'localhost' => array( 'vboctor@localhost' ),
			'dot' => array( 'victor.boctor' ),
			'underscore' => array( 'victor_boctor' ),
			'symbols' => array( "user!" )
		);
	}

	/**
	 * Tear down the test fixture.
	 */
	public function tearDown() {
		foreach( $this->usersToDelete as $t_user_id ) {
			$t_response = $this->builder()->delete( '/users/' . $t_user_id, '' )->send();
			$this->assertEquals( 204, $t_response->getStatusCode() );
		}

		parent::tearDown();
	}

	/**
	 * Capture user id to be deleted in tearDown
	 *
	 * return int|bool The user id or false if no user was created.
	 */
	private function deleteUserIfCreated( $p_response ) {
		$t_user_id = false;

		if( $p_response->getStatusCode() == 201 ) {
			$t_body = json_decode( $p_response->getBody(), true );
			$t_user = $t_body['user'];
			$t_user_id = (int)$t_user['id'];
			$this->usersToDelete[] = $t_user_id;
		}

		return $t_user_id;
	}
}