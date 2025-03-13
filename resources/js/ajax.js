async function post(el) {
  const showId = el.getAttribute("data-show-id");
  try {
    return await getResult(`/showlist`, "POST", { showId });
  } catch (error) {
    return result(true, ["something went wrong"]);
  }
}

async function get(url) {
  try {
    return await getResult(url, "GET");
  } catch (error) {
    return result(true, ["something went wrong"]);
  }
}

async function del(el) {
  const showId = el.getAttribute("data-show-id");
  const method = "DELETE";
  try {
    return await getResult(`/showlist/${showId}`, method, {
      _METHOD: method,
    });
  } catch (error) {
    return result(true, ["something went wrong"]);
  }
}

async function resendEmail() {
  try {
    return await getResult(`/verify`, "POST");
  } catch (error) {
    return result(true, ["something went wrong"]);
  }
}
async function deleteProfile() {
  try {
    return await getResult(`/profile`, "DELETE", {
      _METHOD: "DELETE",
    });
  } catch (error) {
    return result(true, ["something went wrong"]);
  }
}
async function setStartOfWeekSunday(start) {
  try {
    return await getResult(`/profile`, "PATCH", {
      _METHOD: "PATCH",
      startOfWeekSunday: start,
    });
  } catch (error) {
    return result(true, ["something went wrong"]);
  }
}

function result(error, messages, body = null) {
  return {
    error: error,
    messages: messages,
    body: body,
  };
}

async function getResult(url, method, body = null) {
  const response = await fetch(url, {
    method: method !== "GET" ? "POST" : method,
    headers: {
      "Content-Type": "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    body:
      body || url === "/verify"
        ? JSON.stringify({
            ...body,
            ...getCsrfFields(),
          })
        : null,
  });
  if (!response.ok) {
    if ([400, 403, 404, 422].includes(response.status)) {
      const json = await response.json();
      if (response.status === 422) {
        return result(true, json.errors.showId);
      }
      return result(true, [json.msg]);
    }
    return result(true, ["something went wrong"]);
  }

  const json = await response.json();
  if (method === "GET") {
    return result(false, [], json);
  }
  return result(false, [json.msg]);
}

function getCsrfFields() {
  const csrfNameField = document.querySelector("#csrfName");
  const csrfValueField = document.querySelector("#csrfValue");
  const csrfNameKey = csrfNameField.getAttribute("name");
  const csrfName = csrfNameField.content;
  const csrfValueKey = csrfValueField.getAttribute("name");
  const csrfValue = csrfValueField.content;

  return {
    [csrfNameKey]: csrfName,
    [csrfValueKey]: csrfValue,
  };
}

export { post, del, get, resendEmail, deleteProfile, setStartOfWeekSunday };
