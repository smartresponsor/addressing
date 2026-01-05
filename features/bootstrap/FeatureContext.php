<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */


use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FeatureContext extends WebTestCase implements Context
{
    private ?KernelBrowser $client;
    private $body;
    private $response;

    public function __construct()
    {
        self::bootKernel();
        $this->client = static::createClient();
    }

    /** @Given I have a JSON request body: */
    public function iHaveAJsonRequestBody(PyStringNode $string)
    {
        $this->body = $string->getRaw();
    }

    /** @When I send a :method request to :path */
    public function iSendARequestTo($method, $path)
    {
        $this->client->request($method, $path, [], [], ['CONTENT_TYPE' => 'application/json'], $this->body ?? null);
        $this->response = $this->client->getResponse();
    }

    /** @Then the response status code should be :code */
    public function theResponseStatusCodeShouldBe($code)
    {
        if ((int)$code !== $this->response->getStatusCode()) {
            throw new \Exception("Expected $code but got " . $this->response->getStatusCode());
        }
    }
}
