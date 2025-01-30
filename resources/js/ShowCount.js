export class ShowCount {
  #showCount;

  constructor(showCount) {
    this.#showCount = showCount;
  }

  get showCount() {
    return this.#showCount;
  }

  increment() {
    this.#showCount++;
  }

  decrement() {
    this.#showCount--;
  }
}
