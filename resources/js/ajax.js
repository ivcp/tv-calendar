async function post(el) {
  const showId = el.getAttribute("data-show-id");
  try {
    const response = await request(`/showlist`, "POST", showId);
    return getResult(response);
  } catch (error) {
    return result(true, ["something went wrong"]);
  }
}

async function get(url) {
  try {
    const response = await request(url, "GET");
    return getResult(response, true);
  } catch (error) {
    return result(true, ["something went wrong"]);
  }
}

async function del(el) {
  const showId = el.getAttribute("data-show-id");
  try {
    const response = await request(`/showlist/${showId}`, "DELETE");
    return getResult(response);
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

function request(url, method, showId = undefined) {
  return fetch(url, {
    method: method !== "GET" ? "POST" : method,
    headers: {
      "Content-Type": "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    body:
      method !== "GET"
        ? JSON.stringify({
            _METHOD: method === "DELETE" ? method : undefined,
            showId: showId,
            ...getCsrfFields(),
          })
        : null,
  });
}

async function getResult(response, get = false) {
  if (!response.ok) {
    if ([400, 404, 422].includes(response.status)) {
      const json = await response.json();
      if (response.status === 422) {
        return result(true, json.errors.id);
      }
      return result(true, [json.msg]);
    }
    return result(true, ["something went wrong"]);
  }

  const json = await response.json();
  if (get) {
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

export { post, del, get };
