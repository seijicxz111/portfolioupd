// Dashboard functions

function approve(btn) {
  let row = btn.parentElement.parentElement;
  let status = row.querySelector(".status");
  status.innerText = "Approved";
  status.className = "status approved";
  btn.remove();
  alert("Submission Approved!");
}

