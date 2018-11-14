@tide @skipped
# @TODO remove @skipped once the module is extracted to its own repo.
Feature: Fields for Grant content type

  Ensure that Grant content has the expected fields.

  @api @javascript
  Scenario: The content type has the expected fields (and labels where we can use them).
    Given I am logged in as a user with the "create grant content" permission
    When I visit "node/add/grant"
    And save screenshot
    Then I see field "Title"
    And I should see an "input#edit-title-0-value.required" element

    And I see field "Topic"
    And I should see an "input#edit-field-topic-0-target-id" element
    And I should see an "input#edit-field-topic-0-target-id.required" element

    And I see field "Audience"
    And I should see an "input#edit-field-audience-0-target-id.required" element

    And I should see an "input[name='field_node_primary_site']" element
    And I should see an "input[name^='field_node_site']" element

    And I see field "Introduction Text"
    And I should see an "textarea#edit-field-landing-page-intro-text-0-value" element
    And I should not see an "textarea#edit-field-landing-page-intro-text-0-value.required" element

    And I see field "Summary"
    And I should see a "textarea#edit-field-landing-page-summary-0-value" element
    And I should see a "textarea#edit-field-landing-page-summary-0-value.required" element

    And I see field "Show Social Sharing?"
    And I should see an "input#edit-field-show-social-sharing-value" element
    And I should not see an "input#edit-field-show-social-sharing-value.required" element

    And I see field "Show Contact Us?"
    And I should see an "input#edit-field-landing-page-show-contact-value" element
    And I should not see an "input#edit-field-landing-page-show-contact-value.required" element

    And the "#edit-field-featured-image" element should contain "Featured Image"
    And I should see an "input#edit-field-featured-image-entity-browser-entity-browser-open-modal" element

    And I see field "Tags"
    And I should see an "input#edit-field-tags-0-target-id" element
    And I should not see an "input#edit-field-tags-0-target-id.required" element

    And I see field "Department"
    And I should see an "select#edit-field-node-department" element

    And I see field "Funding Level"
    And I should see an "input#edit-field-node-fundinglevel-0-value" element

    And I should see text matching "Contact"
    And I click "Contact"
    Then I should see text matching "Contact Us"

    # Grant Overview paragraph
    When I click "Grant Details"
    Then I should see text matching "No Overview added yet."
    And I should see the button "Add Grants Overview" in the "content" region
    When I press "Add Grants Overview"
    And I wait for AJAX to finish
    Then I see field "field_node_overview[0][subform][field_paragraph_title][0][value]"
    And I see field "field_node_overview[0][subform][field_paragraph_summary][0][value]"
    And I see field "field_node_overview[0][subform][field_paragraph_cta][0][uri]"
    And I see field "field_node_overview[0][subform][field_paragraph_cta][0][title]"

    # Grant Timeline paragraph
    When I click "Timeline"
    Then I should see text matching "No Timeline added yet."
    And I should see the button "Add Timeline" in the "content" region

    # Grant date field.
    And I should see an "input#edit-field-node-on-going-value" element
    And I should see an "input#edit-field-node-dates-0-value-date" element
    And I should see an "input#edit-field-node-dates-0-value-time" element
    And I should see an "input#edit-field-node-dates-0-end-value-date" element
    And I should see an "input#edit-field-node-dates-0-end-value-time" element
