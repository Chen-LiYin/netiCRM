const { test, expect, chromium } = require("@playwright/test");
const utils = require("./utils.js");

/** @type {import('@playwright/test').Page} */
let page;
const wait_secs = 2000;

test.beforeAll(async () => {
  const browser = await chromium.launch();
  page = await browser.newPage();
});

test.afterAll(async () => {
  await page.close();
});

test.describe.serial("Create Membership Type", () => {
  var organization = utils.makeid(10);
  const membership_type = `typeForTest${Math.floor(Math.random() * 100000)}`; //前面建立的假資料有名字重複，所以用隨機數字來避免重複
  var element;
  test("Create Organization Contact", async ({ page }) => {
    // 1. 進入新增組織頁面 - Navigate via homepage link instead of direct URL

    await page.goto("civicrm/contact/add?reset=1&ct=Organization");

    await page.getByRole("link", { name: "New Organization" }).click();
    await utils.wait(wait_secs);

    // Verify form exists
    await expect(page.locator("form#Contact")).toBeVisible();

    // 2. 點選單位抬頭、輸入隨機名稱並點選儲存
    await page.getByRole("textbox", { name: "Organization Name" }).click();
    await page
      .getByRole("textbox", { name: "Organization Name" })
      .fill(organization);
    await page.getByRole("button", { name: "Save" }).nth(2).click();

    await utils.wait(wait_secs);
    await expect(page).toHaveTitle(new RegExp("^" + organization));
  });

  test("Create Membership Type", async () => {
    await page.goto("civicrm/admin/member/membershipType?action=add&reset=1");
    await utils.wait(wait_secs);

    await page.getByLabel("Name\n     *").click();
    await page.getByLabel("Name\n     *").fill(membership_type);
    await page.getByLabel("Membership Organization").fill(organization);
    await page.locator("#_qf_MembershipType_refresh").click();
    await utils.wait(wait_secs);

    await page
      .getByRole("combobox", { name: "Contribution Type" })
      .selectOption("2");
    await page.locator("#duration_interval").click();
    await page.locator("#duration_interval").fill("1");
    await page.getByRole("combobox", { name: "Duration" }).selectOption("year");
    await page
      .getByRole("combobox", { name: "Period Type" })
      .selectOption("rolling");
    await page.locator('[id="_qf_MembershipType_upload-bottom"]').click();
    await expect(page.getByRole("cell", { name: membership_type })).toHaveText(
      membership_type
    );
  });
  test("Create Membership", async () => {
    var firstName = "test_firstName";
    var lastName = "test_lastName";
    var name = firstName + " " + lastName;
    // go to create membership page
    await page.goto(
      "/civicrm/member/add?reset=1&action=add&context=standalone"
    );

    // create individual data
    await page.locator("#profiles_1").selectOption("4");
    await expect(page.getByRole("dialog")).toBeVisible();

    // element = await utils.findElementByLabel(page, "first_name");
    // await utils.fillInput(element, firstName);
    // element = await utils.findElementByLabel(page, "last_name");
    // await utils.fillInput(element, lastName);
    // await page.locator("#_qf_Edit_next").click();
    // await expect(page.locator("#contact_1")).toHaveValue(name);

    await page.locator("#first_name").fill(firstName);
    await page.locator("#last_name").fill(lastName);
    await page.locator("#_qf_Edit_next").click();
    await expect(page.locator("#contact_1")).toHaveValue(name);

    // select the first option in the membership type and orginization
    element = page.locator('[id="membership_type_id\\[0\\]"]');
    // await utils.selectOption(page.locator(element), { index: 0 });
    await element.selectOption(organization);
    element = page.locator('[id="membership_type_id\\[1\\]"]');
    await element.selectOption(membership_type);
    // pick the first date
    await page.locator("#join_date").click();
    await page.getByRole("link", { name: "1", exact: true }).click();
    await page.locator("#start_date").click();
    await page.getByRole("link", { name: "1", exact: true }).click();
    await page.locator('[id="_qf_Membership_upload-bottom"]').click();
    // await utils.wait(wait_secs);
    await expect(page).toHaveTitle(name + " | netiCRM");
    await expect(page.locator("#option11>tbody")).toContainText(
      membership_type
    );
  });
});
