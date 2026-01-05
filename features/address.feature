Feature: Address management
  In order to manage addresses in the system
  As an API consumer
  I want to create, validate and search addresses

  Scenario: Create a valid address
    Given I have a JSON request body:
      """
      {
        "city": "Київ",
        "country": "UA",
        "street": "Хрещатик 1",
        "zipcode": "01001"
      }
      """
    When I send a "POST" request to "/address"
    Then the response status code should be 201

  Scenario: Search address by city
    When I send a "GET" request to "/address/search?city=Київ"
    Then the response status code should be 200

  Scenario: Fail on invalid zipcode
    Given I have a JSON request body:
      """
      {
        "city": "Київ",
        "country": "UA",
        "street": "Хрещатик 1",
        "zipcode": "01A01"
      }
      """
    When I send a "POST" request to "/address"
    Then the response status code should be 400
