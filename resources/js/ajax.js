async function post(el) {
  const response = await fetch("/showlist", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    body: JSON.stringify({
      showId: el.getAttribute("data-show-id"),
      ...getCsrfFields(),
    }),
  });
}

async function get(url) {
  try {
    const response = await fetch(url, {
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
    });
    if (!response.ok) {
      if ([404].includes(response.status)) {
        const json = await response.json();
        return result(true, [json.msg]);
      }
      return result(true, ["something went wrong"]);
    }

    const json = await response.json();
    return result(false, [], json);
  } catch (error) {
    return result(true, ["something went wrong"]);
  }
}

async function del(el) {
  const showId = el.getAttribute("data-show-id");
  try {
    const response = await fetch(`/showlist/${showId}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        _METHOD: "DELETE",
        ...getCsrfFields(),
      }),
    });

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
    return result(false, [json.msg]);
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
